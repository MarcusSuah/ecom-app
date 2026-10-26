<?php

namespace App\Services\Payments;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Resolves the driver for a provider name, limited to config('payments.enabled').
 */
class PaymentGateways
{
    public function __construct(private Application $app) {}

    /**
     * @return list<string>
     */
    public function enabled(): array
    {
        return array_values((array) config('payments.enabled'));
    }

    public function driver(string $name): PaymentGateway
    {
        if (! in_array($name, $this->enabled(), true)) {
            throw new InvalidArgumentException("The payment provider [{$name}] is not enabled.");
        }

        $config = (array) config("payments.providers.{$name}");

        return match ($name) {
            'fake' => new FakePaymentGateway((string) ($config['webhook_secret'] ?? '')),
            'mtn_momo' => new MtnMomoGateway($config, $this->app->make('cache.store')),
            'flutterwave' => new FlutterwaveGateway($config),
            default => throw new InvalidArgumentException("Unknown payment provider [{$name}]."),
        };
    }

    public static function label(string $name): string
    {
        return match ($name) {
            'fake' => 'Test payment',
            'mtn_momo' => 'MTN Mobile Money',
            'flutterwave' => 'Card or mobile money (Flutterwave)',
            'cash' => 'Cash on pickup',
            default => $name,
        };
    }
}
