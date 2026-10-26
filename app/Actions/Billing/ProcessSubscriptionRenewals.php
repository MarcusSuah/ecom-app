<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Daily housekeeping for every marketplace's subscriptions:
 *
 *  - trials and paid periods that ended are invoiced and become past due,
 *  - free plans simply roll on to the next period,
 *  - past-due subscriptions expire once the grace period runs out,
 *  - cancelled subscriptions expire at their end date.
 */
class ProcessSubscriptionRenewals
{
    public function __construct(private IssueInvoice $issueInvoice) {}

    /**
     * @return array{invoiced: int, renewed: int, expired: int}
     */
    public function handle(): array
    {
        $counts = ['invoiced' => 0, 'renewed' => 0, 'expired' => 0];
        $now = now();

        $this->query()
            ->whereIn('status', [SubscriptionStatus::TRIALING, SubscriptionStatus::ACTIVE])
            ->where('current_period_ends_at', '<=', $now)
            ->chunkById(100, function ($subscriptions) use (&$counts): void {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription, &$counts): void {
                        $periodStartsAt = $subscription->current_period_ends_at;
                        $periodEndsAt = $subscription->interval->addTo($periodStartsAt);

                        if ($subscription->price === 0) {
                            $subscription->update([
                                'current_period_starts_at' => $periodStartsAt,
                                'current_period_ends_at' => $periodEndsAt,
                            ]);
                            $counts['renewed']++;

                            return;
                        }

                        $this->issueInvoice->handle($subscription, $periodStartsAt, $periodEndsAt);
                        $subscription->update(['status' => SubscriptionStatus::PAST_DUE]);
                        $counts['invoiced']++;
                    });
                }
            });

        $graceCutoff = $now->subDays((int) config('billing.grace_days'));

        $this->query()
            ->where('status', SubscriptionStatus::PAST_DUE)
            ->where('current_period_ends_at', '<=', $graceCutoff)
            ->chunkById(100, function ($subscriptions) use (&$counts): void {
                foreach ($subscriptions as $subscription) {
                    $this->expire($subscription);
                    $counts['expired']++;
                }
            });

        $this->query()
            ->where('status', SubscriptionStatus::CANCELLED)
            ->where('ends_at', '<=', $now)
            ->chunkById(100, function ($subscriptions) use (&$counts): void {
                foreach ($subscriptions as $subscription) {
                    $this->expire($subscription);
                    $counts['expired']++;
                }
            });

        return $counts;
    }

    /**
     * Runs across every marketplace, so the tenant scope is lifted.
     *
     * @return Builder<Subscription>
     */
    /**
     * Every subscription except the platform's own marketplace plan, which
     * is never invoiced or expired.
     *
     * @return Builder<Subscription>
     */
    private function query(): Builder
    {
        $system = Tenant::query()->where('slug', config('tenancy.fallback_tenant'))->value('id');

        return Subscription::withoutGlobalScopes()
            ->when($system, fn (Builder $query) => $query->whereNot(fn (Builder $query) => $query
                ->where('subscriber_type', (new Tenant)->getMorphClass())
                ->where('subscriber_id', $system)));
    }

    private function expire(Subscription $subscription): void
    {
        $subscription->update(['status' => SubscriptionStatus::EXPIRED, 'ends_at' => $subscription->ends_at ?? now()]);
        $subscription->invoices()->withoutGlobalScopes()->where('status', InvoiceStatus::PENDING)->update(['status' => InvoiceStatus::VOID]);
    }
}
