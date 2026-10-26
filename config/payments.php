<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment providers
    |--------------------------------------------------------------------------
    |
    | The providers buyers can choose at checkout. "fake" completes every
    | payment at once (local development, tests). Credentials come from the
    | environment only.
    |
    */

    'enabled' => array_filter(explode(',', (string) env('PAYMENT_PROVIDERS', 'fake'))),

    'providers' => [
        'fake' => [
            'webhook_secret' => env('PAYMENTS_FAKE_WEBHOOK_SECRET', 'fake-payments-secret'),
        ],

        'mtn_momo' => [
            // https://sandbox.momodeveloper.mtn.com in the sandbox.
            'base_url' => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
            'environment' => env('MTN_MOMO_ENVIRONMENT', 'sandbox'),
            'collection_user_id' => env('MTN_MOMO_COLLECTION_USER_ID'),
            'collection_api_key' => env('MTN_MOMO_COLLECTION_API_KEY'),
            'collection_subscription_key' => env('MTN_MOMO_COLLECTION_SUBSCRIPTION_KEY'),
            'disbursement_user_id' => env('MTN_MOMO_DISBURSEMENT_USER_ID'),
            'disbursement_api_key' => env('MTN_MOMO_DISBURSEMENT_API_KEY'),
            'disbursement_subscription_key' => env('MTN_MOMO_DISBURSEMENT_SUBSCRIPTION_KEY'),
            // The sandbox only accepts EUR.
            'currency_override' => env('MTN_MOMO_CURRENCY_OVERRIDE'),
        ],

        'flutterwave' => [
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            // Set in the Flutterwave dashboard; sent back in the verif-hash header.
            'webhook_hash' => env('FLUTTERWAVE_WEBHOOK_HASH'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment protection
    |--------------------------------------------------------------------------
    |
    | Payments are held until the buyer confirms receipt, or released
    | automatically this many days after delivery if no dispute is open.
    | The interface calls this "Payment protection"; switch the label to
    | "Escrow" once the legal and licensing position is confirmed.
    |
    */

    'auto_release_days' => (int) env('PAYMENTS_AUTO_RELEASE_DAYS', 3),

    'protection_label' => env('PAYMENTS_PROTECTION_LABEL', 'Payment protection'),

    /*
    | Commission when the seller's plan does not set one (percent of the order total).
    */

    'default_commission_percent' => (float) env('PAYMENTS_DEFAULT_COMMISSION_PERCENT', 5),

];
