<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base currency
    |--------------------------------------------------------------------------
    |
    | Rates are stored as "1 base = rate quote" for every other supported
    | currency (App\Support\Money::currencies()); other pairs are derived.
    |
    */

    'base' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Exchange rate provider
    |--------------------------------------------------------------------------
    |
    | "fixed" uses the rates below (no network). "open_er_api" fetches daily
    | rates from https://open.er-api.com (no key needed). Marketplace admins
    | can override the fetched rate for their marketplace.
    |
    */

    'provider' => env('EXCHANGE_RATE_PROVIDER', 'fixed'),

    'providers' => [
        'fixed' => [
            'rates' => [
                'RWF' => env('EXCHANGE_RATE_FIXED_RWF', '1450'),
            ],
        ],

        'open_er_api' => [
            'base_url' => env('EXCHANGE_RATE_OPEN_ER_API_URL', 'https://open.er-api.com/v6'),
        ],
    ],

];
