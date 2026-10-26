<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Production gets roles, plans and the
     * default marketplace only; other environments also get demo data.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PlatformSeeder::class,
            DemoCatalogueSeeder::class,
            DemoCatalogueSeeder::class,
            DemoSeeder::class,
            DemoOrderSeeder::class,
            DemoRfqSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
            // Photos, logos and details for every business (php artisan db:seed --class=DemoMediaSeeder to top up).
            $this->call(DemoMediaSeeder::class);
        }
    }
}
