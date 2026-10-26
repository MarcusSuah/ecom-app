<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The current marketplace's editable settings, with defaults. Read on most
 * public pages (footer), so they are cached per marketplace and the cache is
 * cleared whenever they are saved.
 */
class MarketplaceSettings
{
    /**
     * Every key an admin can set, with its default.
     *
     * @var array<string, string|null>
     */
    public const DEFAULTS = [
        'tagline' => 'Wholesale from verified suppliers, and local shops with pickup or delivery near you.',
        'about' => null,
        'support_phone' => null,
        'whatsapp_number' => null,
        'address' => null,
        'business_hours' => 'Mon–Fri 08:00–18:00, Sat 09:00–13:00',
        'facebook_url' => null,
        'instagram_url' => null,
        'x_url' => null,
        'linkedin_url' => null,
        // "1": new accounts wait for an admin before they can use the site.
        'require_account_approval' => '1',
        // VAT included in prices, used to show the tax part of sales in reports.
        'vat_percent' => '18',
        // The marketplace's logo on the public disk (SettingsController::updateLogo).
        'logo_path' => null,
    ];

    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        $tenantId = $this->currentTenant->id();

        if ($tenantId === null) {
            return self::DEFAULTS;
        }

        return Cache::remember(self::cacheKey($tenantId), now()->addHour(), fn (): array => [
            ...self::DEFAULTS,
            ...Setting::query()->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')->all(),
        ]);
    }

    public function requiresAccountApproval(): bool
    {
        return $this->get('require_account_approval') === '1';
    }

    public function logoUrl(): ?string
    {
        $path = $this->get('logo_path');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function vatPercent(): float
    {
        return (float) $this->get('vat_percent');
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Store the given keys (unknown keys are ignored); empty values fall
     * back to the default.
     *
     * @param  array<string, string|null>  $values
     */
    public function save(array $values): void
    {
        $tenantId = $this->currentTenant->require()->id;

        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            $value = is_string($value) ? trim($value) : null;

            $value === null || $value === ''
                ? Setting::query()->where('key', $key)->delete()
                : Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::cacheKey($tenantId));
    }

    private static function cacheKey(int $tenantId): string
    {
        return "tenant:{$tenantId}:settings";
    }
}
