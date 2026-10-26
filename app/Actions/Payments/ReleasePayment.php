<?php

namespace App\Actions\Payments;

use App\Actions\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlanLimit;
use App\Models\Company;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReleasePayment
{
    public function __construct(private OrderStateMachine $stateMachine) {}

    /**
     * Pay the held money out to the seller: the payment becomes released,
     * a payout is recorded net of commission, and the order completes.
     *
     * Only three callers may do this: the buyer confirming receipt, the
     * auto-release job, and an admin resolving a dispute (who may release
     * an order that never reached "delivered").
     */
    public function handle(Order $order, ?User $actor, string $note, bool $resolvingDispute = false): Payout
    {
        return DB::transaction(function () use ($order, $actor, $note, $resolvingDispute): Payout {
            $payment = Payment::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->where('status', PaymentStatus::HELD)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw ValidationException::withMessages(['order' => 'There is no held payment to release for this order.']);
            }

            if (! $resolvingDispute && $order->hasOpenDispute()) {
                throw ValidationException::withMessages(['order' => 'A dispute is open on this order; an admin will decide.']);
            }

            $payment->forceFill(['status' => PaymentStatus::RELEASED, 'released_at' => now()])->save();

            $rate = self::commissionRate($order->seller);
            $commission = (int) round($payment->amount * $rate / 100);

            $payout = new Payout;
            $payout->forceFill([
                'tenant_id' => $order->tenant_id,
                'company_id' => $order->seller_company_id,
                'order_id' => $order->id,
                'gross' => $payment->amount,
                'commission_rate' => $rate,
                'commission' => $commission,
                'net' => $payment->amount - $commission,
                'currency' => $payment->currency,
                'status' => PayoutStatus::PENDING,
            ])->save();

            if ($resolvingDispute && $order->status !== OrderStatus::DELIVERED) {
                // A dispute can be settled for the seller before the order was marked delivered.
                $this->stateMachine->transition($order, OrderStatus::DELIVERED, $actor, 'Settled by dispute resolution');
                $order->refresh();
            }

            $this->stateMachine->transition($order, OrderStatus::COMPLETED, $actor, $note);

            return $payout;
        });
    }

    /**
     * The seller plan's commission, or the platform default.
     */
    public static function commissionRate(Company $seller): float
    {
        $rate = $seller->planLimit(PlanLimit::COMMISSION_PERCENT);

        return is_int($rate) ? (float) $rate : (float) config('payments.default_commission_percent');
    }
}
