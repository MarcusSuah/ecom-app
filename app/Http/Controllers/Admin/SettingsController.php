<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Support\Audit;
use App\Support\CurrentTenant;
use App\Support\MarketplaceSettings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The marketplace's own details: name, contact, tagline and social links,
 * shown in the storefront header and footer. Guarded by settings.manage.
 */
class SettingsController extends Controller
{
    public function edit(CurrentTenant $currentTenant, MarketplaceSettings $settings): Response
    {
        $tenant = $currentTenant->require();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'name' => $tenant->name,
                'support_email' => $tenant->support_email,
                'default_currency' => $tenant->default_currency,
                ...$settings->all(),
                'logo_url' => $settings->logoUrl(),
            ],
            'currencies' => Money::currencies(),
            'marketplace' => ['slug' => $tenant->slug, 'domain' => $tenant->domain],
        ]);
    }

    /**
     * The logo shown in the storefront header and footer and the dashboard.
     * Kept as uploaded (PNG and WebP keep their transparency).
     */
    public function updateLogo(Request $request, CurrentTenant $currentTenant, MarketplaceSettings $settings): RedirectResponse
    {
        $request->validate(
            ['logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']],
            ['logo.mimes' => 'Use a JPG, PNG or WebP image.', 'logo.max' => 'The logo must be 5 MB or smaller.'],
        );

        $previous = $settings->get('logo_path');
        $path = $request->file('logo')->store("marketplaces/{$currentTenant->require()->id}", 'public')
            ?: throw new RuntimeException('The logo could not be stored.');
        $settings->save(['logo_path' => $path]);

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        Audit::record('update', 'settings', 'Changed the marketplace logo');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo updated.']);

        return back();
    }

    public function destroyLogo(MarketplaceSettings $settings): RedirectResponse
    {
        $previous = $settings->get('logo_path');
        $settings->save(['logo_path' => null]);

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo removed.']);

        return back();
    }

    public function update(UpdateSettingsRequest $request, CurrentTenant $currentTenant, MarketplaceSettings $settings): RedirectResponse
    {
        $tenant = $currentTenant->require();
        $before = [...Arr::only($tenant->toArray(), ['name', 'support_email', 'default_currency']), ...$settings->all()];

        $tenant->update($request->safe()->only(['name', 'support_email', 'default_currency']));
        $settings->save($request->safe()->except(['name', 'support_email', 'default_currency']));

        $after = [...Arr::only($tenant->fresh()->toArray(), ['name', 'support_email', 'default_currency']), ...$settings->all()];
        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

        if ($changed !== []) {
            Audit::record('update', 'settings', 'Updated marketplace settings', Arr::only($before, $changed), Arr::only($after, $changed));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Settings saved.']);

        return back();
    }
}
