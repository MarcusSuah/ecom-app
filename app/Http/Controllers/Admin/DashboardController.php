<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Dashboard\DashboardRange;
use App\Actions\Dashboard\SalesTrend;
use App\Enums\CompanyType;
use App\Enums\DisputeStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProductStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController as MemberDashboard;
use App\Models\Company;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;
use App\Models\Verification;
use App\Support\CurrentTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The marketplace at a glance. Each card is also a tab: choosing it loads
 * its table below (only that table is reloaded).
 */
class DashboardController extends Controller
{
    /** @var list<string> */
    public const TABS = ['members', 'suppliers', 'shops', 'verified', 'pending_verifications', 'orders', 'disputes', 'payouts'];

    public function __invoke(Request $request, CurrentTenant $currentTenant, SalesTrend $salesTrend): Response
    {
        $tenant = $currentTenant->require();
        $range = DashboardRange::fromRequest($request);
        $tab = in_array($request->string('tab')->toString(), self::TABS, true) ? $request->string('tab')->toString() : 'orders';

        // Counted from the database; cached briefly because the page is hit often.
        $counts = Cache::remember("tenant:{$tenant->id}:admin-stats", now()->addMinute(), fn (): array => [
            'members' => User::query()->whereHas('roles')->count(),
            'suppliers' => Company::query()->ofType(CompanyType::SUPPLIER)->count(),
            'shops' => Company::query()->ofType(CompanyType::SHOP)->count(),
            'verified' => Company::query()->ofType(CompanyType::SUPPLIER)->verified()->count(),
            'pending_verifications' => Verification::query()->where('status', VerificationStatus::PENDING)->count(),
            'disputes' => Dispute::query()->where('status', DisputeStatus::OPEN)->count(),
            'payouts' => Payout::query()->where('status', PayoutStatus::PENDING)->count(),
        ]);

        $trend = $salesTrend->handle(Order::query(), $range, $tenant->default_currency);
        $subscription = $tenant->currentSubscription();

        return Inertia::render('Admin/Dashboard', [
            'range' => $range->days,
            'currency' => $tenant->default_currency,
            'currencySubunits' => Money::subunits($tenant->default_currency),
            'counts' => [...$counts, 'orders' => $trend['orders']],
            'kpis' => [
                'gmv' => Money::format($trend['revenue'], $tenant->default_currency),
                'gmv_change' => MemberDashboard::change($trend['revenue'], $trend['previous_revenue']),
                'orders_change' => MemberDashboard::change($trend['orders'], $trend['previous_orders']),
                'commission' => Money::format((int) Payout::query()->where('created_at', '>=', $range->from)->sum('commission'), $tenant->default_currency),
                'pending_payouts' => Money::format((int) Payout::query()->where('status', PayoutStatus::PENDING)->sum('net'), $tenant->default_currency),
            ],
            'trend' => $trend,
            'byStatus' => MemberDashboard::countByStatus(Order::query()->where('placed_at', '>=', $range->from)),
            'tab' => $tab,
            'table' => fn (): array => $this->table($tab),
            'marketplacePlan' => $subscription?->toSummary(),
        ]);
    }

    /**
     * The ten most recent rows behind a card.
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function table(string $tab): array
    {
        $table = match ($tab) {
            'members' => [
                'columns' => ['Name', 'Email', 'Roles', 'Joined'],
                'rows' => User::query()->whereHas('roles')->with('roles:id,name')->latest('id')->limit(10)->get()
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'cells' => [$user->name, $user->email, $user->roles->pluck('name')->join(', '), $user->created_at?->toISOString()],
                    ])->all(),
            ],
            'suppliers', 'shops', 'verified' => [
                'columns' => ['Company', 'City', 'Verification', $tab === 'shops' ? 'Items' : 'Listed products', 'Joined'],
                'rows' => Company::query()
                    ->ofType($tab === 'shops' ? CompanyType::SHOP : CompanyType::SUPPLIER)
                    ->when($tab === 'verified', fn ($query) => $query->verified())
                    ->with('latestVerification')
                    ->withCount([
                        'products as listed_count' => fn (Builder $query) => $query->where('status', ProductStatus::ACTIVE),
                    ])
                    ->latest('id')->limit(10)->get()
                    ->map(fn (Company $company) => [
                        'id' => $company->id,
                        'cells' => [
                            $company->name,
                            $company->city ?? '—',
                            ucfirst($company->latestVerification->status->value ?? 'not submitted'),
                            $tab === 'shops' ? (int) $company->shop?->products()->count() : (int) $company->getAttribute('listed_count'),
                            $company->created_at?->toISOString(),
                        ],
                    ])->all(),
            ],
            'pending_verifications' => [
                'columns' => ['Company', 'Type', 'Registration no.', 'Submitted'],
                'rows' => Verification::query()->where('status', VerificationStatus::PENDING)->with('company:id,name,type')->oldest('id')->limit(10)->get()
                    ->map(fn (Verification $verification) => [
                        'id' => $verification->id,
                        'link' => route('admin.verifications.show', $verification, absolute: false),
                        'cells' => [$verification->company->name, $verification->company->type->label(), $verification->registration_number, $verification->created_at?->toISOString()],
                    ])->all(),
            ],
            'orders' => [
                'columns' => ['Order', 'Buyer', 'Seller', 'Total', 'Status', 'Placed'],
                'rows' => Order::query()->with(['buyer:id,name', 'seller:id,name'])->latest('placed_at')->limit(10)->get()
                    ->map(fn (Order $order) => [
                        'id' => $order->id,
                        'cells' => [$order->number, $order->buyer->name, $order->seller->name, Money::format($order->total, $order->currency), $order->status->label(), $order->placed_at?->toISOString()],
                    ])->all(),
            ],
            'disputes' => [
                'columns' => ['Order', 'Buyer', 'Reason', 'Opened'],
                'rows' => Dispute::query()->where('status', DisputeStatus::OPEN)->with(['order:id,number,buyer_id', 'order.buyer:id,name'])->oldest('id')->limit(10)->get()
                    ->map(fn (Dispute $dispute) => [
                        'id' => $dispute->id,
                        'link' => route('admin.disputes.show', $dispute, absolute: false),
                        'cells' => [$dispute->order->number, $dispute->order->buyer->name, $dispute->reason->label(), $dispute->created_at?->toISOString()],
                    ])->all(),
            ],
            'payouts' => [
                'columns' => ['Seller', 'Order', 'Net to pay', 'Recorded'],
                'rows' => Payout::query()->where('status', PayoutStatus::PENDING)->with(['company:id,name', 'order:id,number'])->oldest('id')->limit(10)->get()
                    ->map(fn (Payout $payout) => [
                        'id' => $payout->id,
                        'cells' => [$payout->company->name, $payout->order->number, Money::format($payout->net, $payout->currency), $payout->created_at?->toISOString()],
                    ])->all(),
            ],
            default => throw new InvalidArgumentException("Unknown dashboard tab [{$tab}]."),
        };

        return ['columns' => $table['columns'], 'rows' => array_values($table['rows'])];
    }
}
