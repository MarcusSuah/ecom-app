<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery distance
    |--------------------------------------------------------------------------
    |
    | Delivery distance is the straight-line (haversine) distance multiplied by
    | this allowance for roads not running in straight lines.
    |
    */

    'road_factor' => (float) env('MARKETPLACE_ROAD_FACTOR', 1.3),

    /*
    | Shop opening hours are read in this timezone.
    */

    'timezone' => env('MARKETPLACE_TIMEZONE', 'Africa/Kigali'),

    /*
    |--------------------------------------------------------------------------
    | Default superadmin
    |--------------------------------------------------------------------------
    |
    | Created by PlatformSeeder in every environment. It runs the platform and
    | holds every permission. In production set a strong password: without
    | one, a random password is generated and written to the log.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Activity log
    |--------------------------------------------------------------------------
    |
    | Sign-ins and what signed-in members do are recorded for admins. Entries
    | are deleted (daily) once they are six months old. The location lookup
    | for sign-ins is off unless an HTTPS endpoint with "{ip}" is set (for
    | example https://ipwho.is/{ip}?fields=success,country,region,city).
    |
    */

    'activity' => [
        'retention_months' => (int) env('ACTIVITY_RETENTION_MONTHS', 6),
        'geoip_enabled' => (bool) env('ACTIVITY_GEOIP_ENABLED', false),
        'geoip_endpoint' => env('ACTIVITY_GEOIP_ENDPOINT'),
    ],

    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Super Admin'),
        'email' => env('SUPERADMIN_EMAIL', 'superadmin@example.com'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Neighbourhoods
    |--------------------------------------------------------------------------
    |
    | Quick picks for setting a location without a map: approximate centre
    | points of Kigali neighbourhoods.
    |
    */

    'neighbourhoods' => [
        ['name' => 'City Centre (CBD)', 'lat' => -1.9441, 'lng' => 30.0619],
        ['name' => 'Gatsata', 'lat' => -1.9180, 'lng' => 30.0700],
        ['name' => 'Gikondo', 'lat' => -1.9758, 'lng' => 30.0778],
        ['name' => 'Gisozi', 'lat' => -1.9236, 'lng' => 30.0590],
        ['name' => 'Kacyiru', 'lat' => -1.9355, 'lng' => 30.0828],
        ['name' => 'Kagugu', 'lat' => -1.9192, 'lng' => 30.0866],
        ['name' => 'Kanombe', 'lat' => -1.9686, 'lng' => 30.1400],
        ['name' => 'Kibagabaga', 'lat' => -1.9302, 'lng' => 30.1186],
        ['name' => 'Kicukiro', 'lat' => -1.9806, 'lng' => 30.1031],
        ['name' => 'Kimihurura', 'lat' => -1.9500, 'lng' => 30.0900],
        ['name' => 'Kimironko', 'lat' => -1.9496, 'lng' => 30.1260],
        ['name' => 'Kinyinya', 'lat' => -1.9100, 'lng' => 30.1000],
        ['name' => 'Nyabugogo', 'lat' => -1.9389, 'lng' => 30.0453],
        ['name' => 'Nyamirambo', 'lat' => -1.9784, 'lng' => 30.0447],
        ['name' => 'Nyarutarama', 'lat' => -1.9330, 'lng' => 30.1050],
        ['name' => 'Rebero', 'lat' => -1.9930, 'lng' => 30.0725],
        ['name' => 'Remera', 'lat' => -1.9579, 'lng' => 30.1063],
    ],

];
