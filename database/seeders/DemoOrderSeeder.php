<?php

namespace Database\Seeders;

use App\Actions\Cart\ManageCart;
use App\Actions\Checkout\PlaceOrders;
use App\Actions\Disputes\OpenDispute;
use App\Actions\Orders\ConfirmReceipt;
use App\Actions\Orders\CreateWholesaleOrder;
use App\Actions\Orders\OrderStateMachine;
use App\Actions\Payments\InitiatePayment;
use App\Enums\DisputeReason;
use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProductStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Orders for buyer@example.com at every stage of the payment flow, built
 * through the real actions so payments, history and payouts line up.
 * Payments use the fake provider, which succeeds immediately.
 */
class DemoOrderSeeder extends Seeder
{
    public function __construct(
        private CreateWholesaleOrder $createWholesaleOrder,
        private InitiatePayment $initiatePayment,
        private OrderStateMachine $stateMachine,
        private ConfirmReceipt $confirmReceipt,
        private OpenDispute $openDispute,
        private ManageCart $manageCart,
        private PlaceOrders $placeOrders,
    ) {}

    public function run(): void
    {
        $buyer = User::query()->where('email', 'buyer@example.com')->firstOrFail();

        if (Order::query()->where('buyer_id', $buyer->id)->exists()) {
            return;
        }

        $products = Product::query()
            ->where('status', ProductStatus::ACTIVE)
            ->whereHas('company', fn ($query) => $query->whereIn('name', ['Rwanda Agro Supplies', 'Nyabugogo Building Materials', 'Thousand Hills Textiles']))
            ->orderBy('id')
            ->get()
            ->unique('company_id')
            ->values();

        // Completed and paid out, completed awaiting payout, due for auto-release, disputed, in production, unpaid.
        $wholesale = [
            [OrderStatus::COMPLETED, 'Kigali', true],
            [OrderStatus::COMPLETED, 'Huye', false],
            [OrderStatus::DELIVERED, 'Musanze', false],
            [OrderStatus::SHIPPED, 'Rubavu', false],
            [OrderStatus::IN_PRODUCTION, 'Kigali', false],
            [OrderStatus::PENDING_PAYMENT, 'Kigali', false],
        ];

        foreach ($wholesale as $index => [$target, $deliverTo, $paidOut]) {
            $product = $products[$index % $products->count()];
            $order = $this->createWholesaleOrder->fromProduct($product, $buyer, $product->moq * (1 + $index % 3), $deliverTo);

            if ($target === OrderStatus::PENDING_PAYMENT) {
                continue;
            }

            $this->pay($order);
            $this->advance($order, [OrderStatus::IN_PRODUCTION, OrderStatus::SHIPPED], $target, $product->company);

            match ($target) {
                OrderStatus::COMPLETED => $this->confirmReceipt->handle($order->fresh(), $buyer),
                OrderStatus::DELIVERED => $this->deliverDaysAgo($order, (int) config('payments.auto_release_days') + 1, $product->company),
                OrderStatus::SHIPPED => $this->openDispute->handle($order->fresh(), $buyer, DisputeReason::NOT_AS_DESCRIBED, 'Half of the bags are a lower grade than the listing shows.'),
                default => null,
            };

            if ($paidOut) {
                $order->payout()->sole()->forceFill(['status' => PayoutStatus::PAID, 'paid_at' => now(), 'reference' => 'BK-DEMO-'.$order->id])->save();
            }
        }

        $this->localOrders($buyer);
    }

    /**
     * Two pickup orders at the corner shop: one ready to collect, one collected.
     */
    private function localOrders(User $buyer): void
    {
        $shop = Company::query()->where('name', 'Kimironko Corner Shop')->first()?->shop;

        if (! $shop || $shop->products()->where('stock', '>', 2)->doesntExist()) {
            return;
        }

        foreach ([OrderStatus::READY_FOR_PICKUP, OrderStatus::COMPLETED] as $target) {
            foreach ($shop->products()->where('stock', '>', 2)->inRandomOrder()->limit(2)->get() as $item) {
                $this->manageCart->add($buyer, $item, 2);
            }
            $this->manageCart->chooseFulfilment($buyer, $shop, Fulfilment::PICKUP);

            [$order] = $this->placeOrders->handle($buyer, $this->manageCart->cartFor($buyer), null);

            $this->pay($order);
            $this->advance($order, [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY_FOR_PICKUP], $target, $shop->company);

            if ($target === OrderStatus::COMPLETED) {
                $this->confirmReceipt->handle($order->fresh(), $buyer);
            }
        }
    }

    private function pay(Order $order): void
    {
        $this->initiatePayment->handle($order, 'fake');
    }

    /**
     * Move the order through the seller's steps until it reaches the target
     * (or the end of the steps, for targets the buyer or the clock reach).
     *
     * @param  list<OrderStatus>  $steps
     */
    private function advance(Order $order, array $steps, OrderStatus $target, Company $seller): void
    {
        foreach ($steps as $step) {
            $this->stateMachine->transition($order->fresh(), $step, $seller->owner);

            if ($step === $target) {
                return;
            }
        }
    }

    private function deliverDaysAgo(Order $order, int $days, Company $seller): void
    {
        $this->stateMachine->transition($order->fresh(), OrderStatus::DELIVERED, $seller->owner, 'Delivered to the warehouse gate');
        $order->forceFill(['delivered_at' => now()->subDays($days)])->save();
    }
}
