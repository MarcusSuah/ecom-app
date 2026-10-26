<?php

namespace App\Actions\Orders;

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShopProduct;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only place an order's status changes, for both order types:
 *
 *   Local:     pending_payment → paid → accepted → preparing
 *              → ready_for_pickup (pickup) | out_for_delivery (delivery)
 *              → delivered → completed
 *   Wholesale: pending_payment → paid → in_production → shipped
 *              → delivered → completed
 *
 *   Cash on pickup (local pickup only): pending_payment → accepted
 *              → preparing → ready_for_pickup → completed, the seller
 *              confirming the cash at handover (CollectCashPayment).
 *
 * Unpaid orders can be cancelled; paid orders that have not completed can
 * be refunded. Cash orders hold no money, so they are cancelled instead. Anything else throws InvalidOrderTransition. Every change is
 * written to order_status_history and announced to buyer and seller.
 *
 * Who may ask for which move (seller, buyer, admin, system) is decided by
 * the calling action or controller, not here.
 */
class OrderStateMachine
{
    public function __construct(private SmsSender $sms) {}

    /**
     * @return list<OrderStatus>
     */
    public static function allowedFrom(OrderStatus $status, OrderType $type, Fulfilment $fulfilment, PaymentMethod $method = PaymentMethod::ONLINE): array
    {
        $refundable = [OrderStatus::REFUNDED];

        if ($method === PaymentMethod::CASH_ON_PICKUP && $type === OrderType::LOCAL && $fulfilment === Fulfilment::PICKUP) {
            return match ($status) {
                OrderStatus::PENDING_PAYMENT => [OrderStatus::ACCEPTED, OrderStatus::CANCELLED],
                OrderStatus::ACCEPTED => [OrderStatus::PREPARING, OrderStatus::CANCELLED],
                OrderStatus::PREPARING => [OrderStatus::READY_FOR_PICKUP, OrderStatus::CANCELLED],
                OrderStatus::READY_FOR_PICKUP => [OrderStatus::COMPLETED, OrderStatus::CANCELLED],
                default => [],
            };
        }

        return match ($type) {
            OrderType::LOCAL => match ($status) {
                OrderStatus::PENDING_PAYMENT => [OrderStatus::PAID, OrderStatus::CANCELLED],
                OrderStatus::PAID => [OrderStatus::ACCEPTED, ...$refundable],
                OrderStatus::ACCEPTED => [OrderStatus::PREPARING, ...$refundable],
                OrderStatus::PREPARING => [$fulfilment === Fulfilment::PICKUP ? OrderStatus::READY_FOR_PICKUP : OrderStatus::OUT_FOR_DELIVERY, ...$refundable],
                OrderStatus::READY_FOR_PICKUP, OrderStatus::OUT_FOR_DELIVERY => [OrderStatus::DELIVERED, ...$refundable],
                OrderStatus::DELIVERED => [OrderStatus::COMPLETED, ...$refundable],
                default => [],
            },
            OrderType::WHOLESALE => match ($status) {
                OrderStatus::PENDING_PAYMENT => [OrderStatus::PAID, OrderStatus::CANCELLED],
                OrderStatus::PAID => [OrderStatus::IN_PRODUCTION, ...$refundable],
                OrderStatus::IN_PRODUCTION => [OrderStatus::SHIPPED, ...$refundable],
                OrderStatus::SHIPPED => [OrderStatus::DELIVERED, ...$refundable],
                OrderStatus::DELIVERED => [OrderStatus::COMPLETED, ...$refundable],
                default => [],
            },
        };
    }

    /**
     * The fulfilment steps the seller moves an order through (not paying,
     * completing or refunding, which belong to the payment flow).
     *
     * @return list<OrderStatus>
     */
    public static function sellerSteps(Order $order): array
    {
        return array_values(array_filter(
            self::allowedFrom($order->status, $order->type, $order->fulfilment, $order->payment_method),
            fn (OrderStatus $status) => ! in_array($status, [OrderStatus::PAID, OrderStatus::COMPLETED, OrderStatus::REFUNDED, OrderStatus::CANCELLED], true),
        ));
    }

    public function canMove(Order $order, OrderStatus $to): bool
    {
        return in_array($to, self::allowedFrom($order->status, $order->type, $order->fulfilment, $order->payment_method), true);
    }

    /**
     * @throws InvalidOrderTransition
     */
    public function transition(Order $order, OrderStatus $to, ?User $actor = null, ?string $note = null): Order
    {
        $order = DB::transaction(function () use ($order, $to, $actor, $note): Order {
            // The caller already holds this order; webhooks and the scheduler run outside its marketplace.
            $order = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            if (! $this->canMove($order, $to)) {
                throw InvalidOrderTransition::for($order, $to);
            }

            if (in_array($to, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
                $this->restock($order);
            }

            // "Sold" counts paid units (cash orders when the cash is collected); a refund takes them back off.
            if ($to === OrderStatus::PAID || ($to === OrderStatus::COMPLETED && $order->paysCash())) {
                $this->countSales($order, 1);
            } elseif ($to === OrderStatus::REFUNDED) {
                $this->countSales($order, -1);
            }

            $order->forceFill([
                'status' => $to,
                'delivered_at' => $to === OrderStatus::DELIVERED ? now() : $order->delivered_at,
                'completed_at' => $to === OrderStatus::COMPLETED ? now() : $order->completed_at,
            ])->save();

            $order->history()->make()->forceFill([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor?->id,
                'note' => $note,
                'created_at' => now(),
            ])->save();

            return $order;
        });

        $this->announce($order);

        return $order;
    }

    /**
     * Local items go back on the shelf; wholesale listings get their units
     * back when they track stock (made-to-order ones have none).
     */
    private function restock(Order $order): void
    {
        foreach ($order->items as $item) {
            match ($item->purchasable_type) {
                (new ShopProduct)->getMorphClass() => ShopProduct::query()->withoutGlobalScopes()->whereKey($item->purchasable_id)->increment('stock', $item->quantity),
                (new Product)->getMorphClass() => Product::query()->withoutGlobalScopes()->whereKey($item->purchasable_id)->whereNotNull('stock')->increment('stock', $item->quantity),
                default => null,
            };
        }
    }

    /**
     * @param  1|-1  $direction
     */
    private function countSales(Order $order, int $direction): void
    {
        foreach ($order->items as $item) {
            $query = match ($item->purchasable_type) {
                (new ShopProduct)->getMorphClass() => ShopProduct::query(),
                (new Product)->getMorphClass() => Product::query(),
                default => null,
            };

            if ($query === null) {
                continue;
            }

            $query = $query->withoutGlobalScopes()->whereKey($item->purchasable_id);

            $direction > 0
                ? $query->increment('units_sold', $item->quantity)
                : $query->where('units_sold', '>=', $item->quantity)->decrement('units_sold', $item->quantity);
        }
    }

    /**
     * In-app and email to both sides; SMS when payment is confirmed and
     * when a delivery leaves the shop.
     */
    private function announce(Order $order): void
    {
        $order->loadMissing(['buyer', 'seller.owner']);

        $order->buyer->notify(new OrderStatusChanged($order));
        $order->seller->owner->notify(new OrderStatusChanged($order));

        $texts = match ($order->status) {
            OrderStatus::PAID => [
                [$order->buyer->phone, "Payment received for order {$order->number}. {$order->seller->name} will prepare it."],
                [$order->seller->phone ?? $order->seller->owner->phone, "New paid order {$order->number}. Open your orders to accept it."],
            ],
            OrderStatus::OUT_FOR_DELIVERY => [
                [$order->buyer->phone, "Order {$order->number} from {$order->seller->name} is on its way."],
            ],
            default => [],
        };

        foreach ($texts as [$phone, $message]) {
            if (! $phone) {
                continue;
            }

            try {
                $this->sms->send($phone, $message);
            } catch (Throwable $exception) {
                Log::warning('Order SMS failed', ['order' => $order->id, 'error' => $exception->getMessage()]);
            }
        }
    }
}
