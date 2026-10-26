<?php

namespace Database\Seeders;

use App\Actions\Billing\ActivatePlanManually;
use App\Enums\CompanyType;
use App\Enums\Role;
use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Seeder;

/**
 * Fifteen local shops around Kigali with 10–30 items each, on the current
 * marketplace. Run by DemoSeeder.
 */
class DemoShopSeeder extends Seeder
{
    /**
     * [shop name, neighbourhood, lat, lng, kind, free delivery from (RWF) or null, pickup only].
     *
     * @var list<array{0: string, 1: string, 2: float, 3: float, 4: string, 5: int|null, 6: bool}>
     */
    private const SHOPS = [
        ['Kimironko Corner Shop', 'Kimironko', -1.9493, 30.1268, 'grocery', 20000, false],
        ['Remera Fresh Mart', 'Remera', -1.9583, 30.1071, 'grocery', 25000, false],
        ['Kacyiru Home & Kitchen', 'Kacyiru', -1.9361, 30.0835, 'home', 50000, false],
        ['Gisozi Office Supplies', 'Gisozi', -1.9230, 30.0583, 'stationery', 30000, false],
        ['Nyamirambo Family Grocery', 'Nyamirambo', -1.9779, 30.0452, 'grocery', 15000, false],
        ['Kicukiro Beauty Corner', 'Kicukiro', -1.9812, 30.1025, 'beauty', 20000, false],
        ['Nyarutarama Deli', 'Nyarutarama', -1.9325, 30.1057, 'grocery', 30000, false],
        ['Kimihurura Pharmacy & Care', 'Kimihurura', -1.9506, 30.0894, 'beauty', null, false],
        ['Gikondo Hardware Point', 'Gikondo', -1.9752, 30.0784, 'hardware', null, true],
        ['Kanombe Mini Market', 'Kanombe', -1.9690, 30.1395, 'grocery', 20000, false],
        ['Nyabugogo Electronics Stall', 'Nyabugogo', -1.9384, 30.0460, 'electronics', 60000, false],
        ['Kibagabaga Home Goods', 'Kibagabaga', -1.9306, 30.1180, 'home', 40000, false],
        ['City Centre Stationers', 'City Centre (CBD)', -1.9447, 30.0612, 'stationery', 25000, false],
        ['Kagugu Fresh Produce', 'Kagugu', -1.9188, 30.0871, 'grocery', 15000, false],
        ['Rebero Hilltop Shop', 'Rebero', -1.9925, 30.0731, 'grocery', 20000, false],
    ];

    /**
     * Items per kind: [name, category, price in RWF].
     *
     * @var array<string, array{category: string, items: list<array{0: string, 1: int}>}>
     */
    private const CATALOGUE = [
        'grocery' => ['category' => 'Food & Beverages', 'items' => [
            ['Bananas (bunch)', 1500], ['Tomatoes, 1 kg', 1200], ['Onions, 1 kg', 1000], ['Irish potatoes, 5 kg', 3500], ['Rice, 5 kg', 7500],
            ['Maize flour, 2 kg', 1800], ['Cooking oil, 1 L', 3200], ['Sugar, 1 kg', 1600], ['Milk, 1 L', 900], ['Eggs (tray of 30)', 4500],
            ['Bread loaf', 1200], ['Avocados (4)', 1000], ['Passion fruit juice, 1 L', 2500], ['Mineral water, 1.5 L', 600], ['Beans, 1 kg', 1300],
            ['Groundnuts, 500 g', 1500], ['Tea leaves, 250 g', 1800], ['Coffee, 250 g', 4500], ['Pineapple', 1500], ['Carrots, 1 kg', 900],
            ['Cabbage', 700], ['Spinach bunch', 500], ['Honey, 500 g', 5000], ['Yoghurt, 500 ml', 1500], ['Salt, 1 kg', 500],
            ['Spaghetti, 500 g', 1200], ['Tomato paste', 800], ['Matoke (bunch)', 4000], ['Sweet potatoes, 2 kg', 1500], ['Pilipili sauce', 1500],
        ]],
        'home' => ['category' => 'Home & Kitchen', 'items' => [
            ['Cooking pot, 5 L', 12000], ['Frying pan', 9500], ['Plastic basin', 3000], ['Water jerrycan, 20 L', 4500], ['Thermos flask', 8000],
            ['Dinner plates (6)', 9000], ['Drinking glasses (6)', 6000], ['Kitchen knife set', 7500], ['Mosquito net', 4500], ['Bucket with lid', 3500],
            ['Broom', 2000], ['Mop and bucket', 9000], ['Charcoal stove', 9500], ['Cutting board', 3000], ['Cutlery set', 6500],
            ['Bed sheets (double)', 15000], ['Bath towel', 6000], ['Storage box', 5500], ['Laundry basket', 4500], ['Doormat', 3500],
        ]],
        'stationery' => ['category' => 'Office & Stationery', 'items' => [
            ['Exercise book, 96 pages', 400], ['A4 paper (ream)', 5500], ['Ballpoint pens (10)', 1500], ['Pencils (12)', 1200], ['Ruler 30 cm', 300],
            ['Geometry set', 2500], ['Box file', 2500], ['Stapler', 4000], ['Calculator', 6500], ['Markers (4)', 2500],
            ['Glue stick', 600], ['Scissors', 1500], ['Envelopes (20)', 2000], ['School bag', 15000], ['Highlighters (4)', 2500],
            ['Sticky notes', 1500], ['Clipboard', 2500], ['Printer ink', 18000],
        ]],
        'beauty' => ['category' => 'Health & Beauty', 'items' => [
            ['Bar soap', 800], ['Toothpaste', 1500], ['Toothbrush', 700], ['Body lotion, 400 ml', 4500], ['Shampoo', 4000],
            ['Petroleum jelly', 1200], ['Hand sanitiser', 2500], ['Sanitary pads', 1500], ['Deodorant', 3500], ['Face wash', 5000],
            ['Hair oil', 3000], ['Razor blades (5)', 1500], ['Cotton wool', 1000], ['Plasters (box)', 1500], ['Sunscreen', 9000],
        ]],
        'hardware' => ['category' => 'Construction Materials', 'items' => [
            ['Cement, 50 kg', 12500], ['Nails, 1 kg', 2500], ['Hammer', 6000], ['Paint brush', 1500], ['Padlock', 4000],
            ['PVC pipe 1/2"', 3000], ['Tap', 5500], ['Wall paint, 4 L', 15000], ['Screwdriver set', 6500], ['Measuring tape', 3000],
            ['Electrical tape', 800], ['Light bulb', 1500],
        ]],
        'electronics' => ['category' => 'Electronics', 'items' => [
            ['Phone charger', 3500], ['USB cable', 2000], ['Power bank 10000 mAh', 18000], ['Earphones', 4500], ['Extension socket', 6500],
            ['LED bulb 9 W', 1500], ['Torch', 4000], ['AA batteries (4)', 2000], ['Radio', 15000], ['Memory card 32 GB', 8000],
            ['Phone case', 3000], ['Bluetooth speaker', 25000],
        ]],
    ];

    public function __construct(private ActivatePlanManually $activatePlanManually) {}

    public function run(): void
    {
        $verifier = User::query()->where('email', 'verifier@example.com')->firstOrFail();
        $recordedBy = User::query()->where('is_platform_admin', true)->firstOrFail();
        $growth = Plan::query()->forSellersOf(Category::query()->value('tenant_id'))->where('name', 'Growth')->firstOrFail();
        $categories = Category::query()->whereNull('parent_id')->pluck('id', 'name');

        foreach (self::SHOPS as $index => [$name, $area, $lat, $lng, $kind, $freeFrom, $pickupOnly]) {
            $company = Company::query()->ofType(CompanyType::SHOP)->where('name', $name)->first()
                ?? $this->newShopCompany($name, $index, $verifier);

            if ($company->shop()->exists()) {
                continue;
            }

            if ($company->subscriptions()->doesntExist()) {
                $this->activatePlanManually->handle($company, $growth, 12, $recordedBy, "DEMO-SHOP-{$company->id}");
            }

            $shop = new Shop([
                'lat' => $lat,
                'lng' => $lng,
                'address_text' => "{$area}, Kigali",
                'landmark' => $index % 2 === 0 ? 'Next to the main road' : null,
                'offers_pickup' => true,
                'offers_delivery' => ! $pickupOnly,
                'base_fee' => 800 + ($index % 3) * 200,
                'per_km_rate' => 250 + ($index % 4) * 50,
                'free_threshold' => $freeFrom,
                'delivery_radius_km' => 4 + $index % 5,
                // Most shops open 07:00–21:00; Sundays shorter; one closes on Sundays.
                'opening_hours' => [
                    ...array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat'], ['open' => '07:00', 'close' => '21:00']),
                    'sun' => $index % 5 === 4 ? null : ['open' => '10:00', 'close' => '18:00'],
                ],
                'currency' => 'RWF',
            ]);
            $shop->company()->associate($company);
            $shop->save();

            $catalogue = self::CATALOGUE[$kind];
            $count = min(count($catalogue['items']), 10 + ($index * 7) % 21);

            foreach (array_slice($catalogue['items'], 0, $count) as $position => [$itemName, $price]) {
                $shop->products()->create([
                    'category_id' => $categories[$catalogue['category']],
                    'name' => $itemName,
                    'description' => null,
                    'price' => $price,
                    'stock' => $position % 9 === 8 ? 0 : 10 + ($position * 13) % 90,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function newShopCompany(string $name, int $index, User $verifier): Company
    {
        $owner = User::query()->firstOrCreate(
            ['email' => 'shop'.($index + 1).'@example.com'],
            ['name' => str($name)->before(' ')->append(' Owner')->toString(), 'password' => 'password', 'email_verified_at' => now()],
        );
        $owner->assignRole(Role::BUYER->value);

        $company = Company::factory()->for($owner, 'owner')->shop()->state(['name' => $name, 'city' => 'Kigali'])->create();

        // Most demo shops are verified; every fourth is still waiting.
        Verification::factory()->for($company)->state($index % 4 === 3
            ? ['status' => VerificationStatus::PENDING]
            : ['status' => VerificationStatus::APPROVED, 'reviewer_id' => $verifier->id, 'reviewed_at' => now()->subDays(10)])->create();

        return $company;
    }
}
