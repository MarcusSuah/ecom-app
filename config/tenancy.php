<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central Domain
    |--------------------------------------------------------------------------
    |
    | Each marketplace (tenant) is served from "{slug}.{central_domain}" or
    | from its own custom domain. Requests to any other host fall back to the
    | tenant below, which keeps "localhost" working during development.
    |
    */

    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', 'localhost'),

    'fallback_tenant' => env('TENANCY_FALLBACK_TENANT', 'mix-ventures'),

];
