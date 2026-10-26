<?php

namespace App\Actions\Orders;

use App\Actions\Payments\ReleasePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The shop hands over a cash-on-pickup order and takes the money. The cash
 * is recorded as a payment, and as a payout already in the seller's hands
 * (its commission is still owed to the marketplace), and the order completes.
 */
class CollectCashPayment
{
    public const PROVIDER = 'cash';

    public function __construct(private OrderStateMachine $stateMachine) {}

    public function handle(Order $order, User $seller): Order
    {
        if (! $order->paysCash() || $order->status !== OrderStatus::READY_FOR_PICKUP) {
            throw ValidationException::withMessages(['order' => 'Mark the order ready for pickup before taking the cash.']);
        }

        return DB::transaction(function () use ($order, $seller): Order {
            $payment = new Payment;
            $payment->forceFill([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'reference' => (string) Str::uuid(),
                'provider' => self::PROVIDER,
                'amount' => $order->total,
                'currency' => $order->currency,
                'status' => PaymentStatus::RELEASED,
                'held_at' => now(),
                'released_at' => now(),
            ])->save();

            $rate = ReleasePayment::commissionRate($order->seller);
            $commission = (int) round($order->total * $rate / 100);
            $payout = new Payout;
            $payout->forceFill([
                'tenant_id' => $order->tenant_id,
                'company_id' => $order->seller_company_id,
                'order_id' => $order->id,
                'gross' => $order->total,
                'commission_rate' => $rate,
                'commission' => $commission,
                'net' => $order->total - $commission,
                'currency' => $order->currency,
                'status' => PayoutStatus::PAID,
                'paid_at' => now(),
                'reference' => 'Cash collected by the shop',
            ])->save();

            return $this->stateMachine->transition($order, OrderStatus::COMPLETED, $seller, 'Cash collected at pickup');
        });
    }
}
