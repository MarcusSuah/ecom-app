<?php

namespace App\Actions\Payments;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AutoReleasePayments
{
    public function __construct(
        private ReleasePayment $releasePayment,
        private CurrentTenant $currentTenant,
    ) {}

    /**
     * Release held payments for orders delivered at least
     * payments.auto_release_days ago with no open dispute, on every
     * marketplace. Returns how many were released.
     */
    public function handle(): int
    {
        $released = 0;

        Order::withoutGlobalScopes()
            ->with('tenant')
            ->where('status', OrderStatus::DELIVERED)
            ->where('delivered_at', '<=', now()->subDays((int) config('payments.auto_release_days')))
            ->whereHas('payments', fn (Builder $query) => $query->withoutGlobalScopes()->where('status', PaymentStatus::HELD))
            ->whereDoesntHave('dispute', fn (Builder $query) => $query->withoutGlobalScopes()->where('status', DisputeStatus::OPEN))
            ->chunkById(100, function ($orders) use (&$released): void {
                foreach ($orders as $order) {
                    try {
                        // Each release runs as its marketplace so plans, disputes and notifications resolve there.
                        $this->currentTenant->runAs($order->tenant, fn () => $this->releasePayment->handle($order, null, 'Released automatically after delivery'));
                        $released++;
                    } catch (ValidationException $exception) {
                        Log::warning('Auto-release skipped', ['order' => $order->id, 'errors' => $exception->errors()]);
                    }
                }
            });

        return $released;
    }
}
