<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The currency a visitor wants prices shown in: their saved preference,
 * else the one chosen this session, else the marketplace default.
 */
class DisplayCurrency
{
    public const SESSION_KEY = 'display_currency';

    public function __construct(
        private CurrentTenant $currentTenant,
        private CurrencyConverter $converter,
    ) {}

    public function code(): string
    {
        $user = $this->request()->user();
        $chosen = $user instanceof User ? $user->preferred_currency : null;
        $chosen ??= $this->request()->hasSession() ? $this->request()->session()->get(self::SESSION_KEY) : null;

        return is_string($chosen) && in_array($chosen, Money::currencies(), true)
            ? $chosen
            : ($this->currentTenant->get()->default_currency ?? (string) config('currency.base'));
    }

    /**
     * Remembers the choice for this session and, when signed in, on the account.
     */
    public function remember(string $currency): void
    {
        $this->request()->session()->put(self::SESSION_KEY, $currency);

        $user = $this->request()->user();

        if ($user instanceof User) {
            $user->forceFill(['preferred_currency' => $currency])->save();
        }
    }

    /**
     * The amount in the visitor's chosen currency, e.g. "USD 10.35"; in its
     * own currency when they match or no rate is known.
     */
    public function format(int $amount, string $currency): string
    {
        $display = $this->code();
        $converted = $display === strtoupper($currency) ? null : $this->converter->convert($amount, $currency, $display);

        return $converted === null ? Money::format($amount, $currency) : Money::format($converted, $display);
    }

    /**
     * The amount in its own currency ("RWF 15,000"), which is what the buyer
     * is charged, when format() showed it converted; null otherwise.
     */
    public function listed(int $amount, string $currency): ?string
    {
        $display = $this->code();

        if ($display === strtoupper($currency) || $this->converter->rate($currency, $display) === null) {
            return null;
        }

        return Money::format($amount, $currency);
    }

    /**
     * Major-unit rates from each currency into the display currency (null
     * when unknown), so pages can convert live totals the same way.
     *
     * @return array<string, float|null>
     */
    public function rates(): array
    {
        $display = $this->code();

        return collect(Money::currencies())->mapWithKeys(fn (string $currency) => [$currency => $this->converter->rate($currency, $display)])->all();
    }

    /**
     * "≈ USD 10.35" when the visitor sees prices in another currency than
     * the amount's own; null when they match or no rate is known.
     */
    public function approximate(int $amount, string $currency): ?string
    {
        $display = $this->code();

        if ($display === strtoupper($currency)) {
            return null;
        }

        $converted = $this->converter->convert($amount, $currency, $display);

        return $converted === null ? null : '≈ '.Money::format($converted, $display);
    }

    /**
     * Resolved on each call: this object may outlive one request (tests, Octane).
     */
    private function request(): Request
    {
        return app(Request::class);
    }
}
