<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateChange;
use App\Support\CurrencyConverter;
use App\Support\CurrentTenant;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The rates this marketplace shows prices with: the shared daily rate, or
 * a manual override set here. Guarded by currency.manage on the routes.
 */
class CurrencyRateController extends Controller
{
    public function index(CurrentTenant $currentTenant): Response
    {
        $base = strtoupper((string) config('currency.base'));
        $quotes = array_values(array_diff(Money::currencies(), [$base]));
        $rates = CurrencyRate::query()
            ->where('base', $base)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $currentTenant->id()))
            ->get();

        $tenantId = $currentTenant->require()->id;

        return Inertia::render('Admin/Currency/Index', [
            'base' => $base,
            // The rate history this marketplace's prices and reports follow, newest first.
            'history' => CurrencyRateChange::query()
                ->affecting($tenantId)
                ->with('changer:id,name')
                ->latest('changed_at')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (CurrencyRateChange $change) => [
                    'id' => $change->id,
                    'quote' => $change->quote,
                    'scope' => $change->tenant_id === null ? 'Daily rate (all marketplaces)' : 'This marketplace',
                    'old_rate' => $change->old_rate !== null ? (float) $change->old_rate : null,
                    'new_rate' => $change->new_rate !== null ? (float) $change->new_rate : null,
                    'source' => $change->source,
                    'changed_by' => $change->changer?->name,
                    'changed_at' => $change->changed_at->toISOString(),
                ])->values(),
            'rates' => collect($quotes)->map(function (string $quote) use ($rates): array {
                $shared = $rates->first(fn (CurrencyRate $rate) => $rate->quote === $quote && $rate->tenant_id === null);
                $override = $rates->first(fn (CurrencyRate $rate) => $rate->quote === $quote && $rate->tenant_id !== null);

                return [
                    'quote' => $quote,
                    'shared' => $shared ? ['rate' => (float) $shared->rate, 'source' => $shared->source, 'fetched_at' => $shared->fetched_at->toISOString()] : null,
                    'override' => $override ? ['rate' => (float) $override->rate, 'set_at' => $override->fetched_at->toISOString()] : null,
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, CurrentTenant $currentTenant, CurrencyConverter $converter): RedirectResponse
    {
        $base = strtoupper((string) config('currency.base'));
        $validated = $request->validate([
            'quote' => ['required', 'string', Rule::in(array_diff(Money::currencies(), [$base]))],
            'rate' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
        ], [
            'rate.required' => 'Enter the rate.',
            'rate.gt' => 'The rate must be more than zero.',
        ]);

        CurrencyRate::query()->updateOrCreate(
            ['tenant_id' => $currentTenant->require()->id, 'base' => $base, 'quote' => $validated['quote']],
            ['rate' => $validated['rate'], 'source' => CurrencyRate::SOURCE_MANUAL, 'fetched_at' => now()],
        );
        $converter->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Prices now use 1 {$base} = {$validated['rate']} {$validated['quote']}."]);

        return back();
    }

    public function destroy(string $quote, CurrentTenant $currentTenant, CurrencyConverter $converter): RedirectResponse
    {
        CurrencyRate::query()
            ->where('tenant_id', $currentTenant->require()->id)
            ->where('quote', strtoupper($quote))
            ->get()
            ->each(fn (CurrencyRate $rate) => $rate->delete());
        $converter->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Back to the daily rate.']);

        return back();
    }
}
