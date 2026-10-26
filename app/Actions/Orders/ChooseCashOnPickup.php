<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The buyer chooses to pay a pickup order in cash at the shop instead of
 * online. Nothing is charged; the shop accepts the order and takes the
 * cash at handover (CollectCashPayment).
 */
class ChooseCashOnPickup
{
    public function handle(Order $order, User $buyer): Order
    {
        if (! $order->canPayCash()) {
            throw ValidationException::withMessages(['order' => 'Cash is only possible for orders you collect from the shop.']);
        }

        if ($order->status !== OrderStatus::PENDING_PAYMENT || $order->payments()->whereIn('status', [PaymentStatus::INITIATED, ...PaymentStatus::secured()])->exists()) {
            throw ValidationException::withMessages(['order' => 'This order is already being paid online.']);
        }

        DB::transaction(function () use ($order, $buyer): void {
            $order->forceFill(['payment_method' => PaymentMethod::CASH_ON_PICKUP])->save();
            $order->history()->make()->forceFill([
                'from_status' => OrderStatus::PENDING_PAYMENT,
                'to_status' => OrderStatus::PENDING_PAYMENT,
                'changed_by' => $buyer->id,
                'note' => 'Will pay cash on pickup',
                'created_at' => now(),
            ])->save();
        });

        $order->loadMissing('seller.owner');
        $order->seller->owner->notify(new OrderStatusChanged($order));

        return $order;
    }
}
