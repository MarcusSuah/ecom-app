<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription Gateway
    |--------------------------------------------------------------------------
    |
    | The driver that collects subscription payments. "fake" completes every
    | checkout at once and is meant for local development and tests. Real
    | drivers (MTN MoMo, Flutterwave) are added with the Phase 5 payments work.
    |
    */

    'gateway' => env('BILLING_GATEWAY', 'fake'),

    'gateways' => [
        'fake' => [
            'webhook_secret' => env('BILLING_FAKE_WEBHOOK_SECRET', 'fake-webhook-secret'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    |
    | Days a subscription stays usable as "past due" after its period ends
    | without payment, before it expires.
    |
    */

    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

];
