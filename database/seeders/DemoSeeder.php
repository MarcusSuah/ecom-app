<?php

namespace Database\Seeders;

use App\Actions\Billing\ActivatePlanManually;
use App\Actions\Tenants\ProvisionMarketplaceDefaults;
use App\Enums\CompanyType;
use App\Enums\ContactTopic;
use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Enums\VerificationStatus;
use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Verification;
use App\Support\CurrentTenant;
use App\Support\MarketplaceSettings;
use Illuminate\Database\Seeder;

/**
 * Sample data for development and demos. Not run in production.
 * Every account's password is "password".
 */
class DemoSeeder extends Seeder
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private ActivatePlanManually $activatePlanManually,
        private ProvisionMarketplaceDefaults $provisionDefaults,
    ) {}

    public function run(): void
    {
        // The default superadmin from PlatformSeeder records the demo subscriptions.
        $platformAdmin = User::query()->where('email', config('marketplace.superadmin.email'))->firstOrFail();

        $mixVentures = Tenant::query()->where('slug', config('tenancy.fallback_tenant'))->firstOrFail();
        $this->subscribeMarketplace($mixVentures, 'Scale', $platformAdmin);
        $this->currentTenant->runAs($mixVentures, fn () => $this->seedMixVentures($platformAdmin));

        $second = Tenant::query()->firstOrCreate(
            ['slug' => 'kigali-fresh'],
            ['name' => 'Kigali Fresh Market', 'default_currency' => 'RWF', 'support_email' => 'hello@kigalifresh.example'],
        );
        $this->provisionDefaults->handle($second);
        $this->subscribeMarketplace($second, 'Launch', $platformAdmin);
        $this->currentTenant->runAs($second, fn () => $this->seedSecondMarketplace());
    }

    private function seedMixVentures(User $platformAdmin): void
    {
        $this->user('Marketplace Admin', 'admin@example.com')->assignRole(Role::ADMIN->value);
        $verifier = $this->user('Grace Verifier', 'verifier@example.com');
        $verifier->assignRole(Role::VERIFIER->value);
        $this->user('Bea Buyer', 'buyer@example.com')->assignRole(Role::BUYER->value);

        $companies = [
            // [owner email, name, type, verification, city]
            ['supplier@example.com', 'Rwanda Agro Supplies', CompanyType::SUPPLIER, VerificationStatus::APPROVED, 'Kigali'],
            ['shop@example.com', 'Kimironko Corner Shop', CompanyType::SHOP, VerificationStatus::APPROVED, 'Kigali'],
            ['supplier2@example.com', 'Nyabugogo Building Materials', CompanyType::SUPPLIER, VerificationStatus::APPROVED, 'Kigali'],
            ['supplier3@example.com', 'Thousand Hills Textiles', CompanyType::SUPPLIER, VerificationStatus::APPROVED, 'Huye'],
            ['supplier4@example.com', 'Lake Kivu Coffee Exporters', CompanyType::SUPPLIER, VerificationStatus::APPROVED, 'Rubavu'],
            ['supplier5@example.com', 'Kigali Packaging Solutions', CompanyType::SUPPLIER, VerificationStatus::PENDING, 'Kigali'],
            ['supplier6@example.com', 'Musanze Electronics Hub', CompanyType::SUPPLIER, VerificationStatus::REJECTED, 'Musanze'],
            ['shop2@example.com', 'Remera Fresh Mart', CompanyType::SHOP, VerificationStatus::APPROVED, 'Kigali'],
            ['shop3@example.com', 'Kacyiru Home & Kitchen', CompanyType::SHOP, VerificationStatus::PENDING, 'Kigali'],
            ['shop4@example.com', 'Gisozi Office Supplies', CompanyType::SHOP, null, 'Kigali'],
        ];

        foreach ($companies as [$email, $name, $type, $verification, $city]) {
            $owner = $this->user(str($name)->before(' ')->append(' Owner')->toString(), $email);

            $company = Company::query()->where('owner_id', $owner->id)->first()
                ?? Company::factory()->for($owner, 'owner')->state(['type' => $type, 'name' => $name, 'city' => $city])->create();

            if ($verification && $company->verifications()->doesntExist()) {
                $factory = Verification::factory()->for($company);
                $factory = match ($verification) {
                    VerificationStatus::APPROVED => $factory->state(['status' => $verification, 'reviewer_id' => $verifier->id, 'reviewed_at' => now()->subDays(rand(5, 60))]),
                    VerificationStatus::REJECTED => $factory->state(['status' => $verification, 'reviewer_id' => $verifier->id, 'reviewed_at' => now()->subDays(3), 'rejection_reason' => 'The registration certificate is expired. Please upload the current one.']),
                    VerificationStatus::PENDING => $factory,
                };
                $factory->create();
            }
        }

        // One supplier on the Pro plan, featured on the marketplace.
        $featured = Company::query()->where('name', 'Rwanda Agro Supplies')->firstOrFail();
        if ($featured->subscriptions()->doesntExist()) {
            $pro = Plan::query()->forSellersOf($featured->tenant_id)->where('name', 'Pro')->firstOrFail();
            $this->activatePlanManually->handle($featured, $pro, 12, $platformAdmin, 'DEMO-PRO');
        }
        $featured->forceFill(['is_featured' => true])->save();

        $this->call([DemoCatalogueSeeder::class, DemoRfqSeeder::class, DemoShopSeeder::class, DemoOrderSeeder::class]);

        // Contact details and links shown in the storefront footer.
        app(MarketplaceSettings::class)->save([
            'tagline' => 'Wholesale from verified suppliers, and local shops with pickup or delivery near you.',
            'about' => 'A wholesale marketplace connecting buyers in Rwanda and the region with verified suppliers and local shops.',
            'support_phone' => '+250 788 000 111',
            'whatsapp_number' => '+250 788 000 111',
            'address' => 'KG 7 Ave, Kacyiru, Kigali',
            'facebook_url' => 'https://facebook.com/mixventures',
            'instagram_url' => 'https://instagram.com/mixventures',
        ]);

        // Stock on some wholesale listings, so every badge shows: plenty, low and none left.
        Product::query()->where('status', ProductStatus::ACTIVE)->orderBy('id')->get()->each(function (Product $product, int $index): void {
            $stock = match ($index % 6) {
                0 => $product->moq * 40,
                1 => $product->moq * 2,
                2 => $product->moq - 1,
                default => null,
            };
            $product->forceFill(['stock' => $stock])->saveQuietly();
        });

        // Something in the admins' contact inbox.
        if (ContactMessage::query()->doesntExist()) {
            ContactMessage::factory()->create(['name' => 'Jean Habimana', 'email' => 'jean@example.rw', 'topic' => ContactTopic::SELLING, 'message' => 'We produce honey in Nyamagabe. Can we list in USD for export buyers?']);
            ContactMessage::factory()->handled()->create(['name' => 'Aline Uwase', 'email' => 'aline@example.rw', 'topic' => ContactTopic::PAYMENTS, 'message' => 'How long does a refund take after a seller cancels?']);
        }
    }

    private function seedSecondMarketplace(): void
    {
        $this->user('Fresh Admin', 'admin@kigalifresh.example')->assignRole(Role::ADMIN->value);

        // The supplier account also sells here: one login, a separate company per marketplace.
        $supplier = $this->user('Rwanda Owner', 'supplier@example.com');
        if (Company::query()->where('owner_id', $supplier->id)->doesntExist()) {
            Company::factory()->for($supplier, 'owner')->shop()->verified()->state(['name' => 'Agro Fresh Stall'])->create();
        }
    }

    private function subscribeMarketplace(Tenant $tenant, string $planName, User $platformAdmin): void
    {
        if ($tenant->subscriptions()->exists()) {
            return;
        }

        $plan = Plan::query()->forMarketplaces()->where('name', $planName)->firstOrFail();
        $this->activatePlanManually->handle($tenant, $plan, 12, $platformAdmin, 'DEMO-'.strtoupper($tenant->slug));
    }

    private function user(string $name, string $email): User
    {
        return User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
    }
}
