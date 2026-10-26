<?php

namespace Database\Seeders;

use App\Actions\Billing\ActivatePlanManually;
use App\Actions\Products\SaveProduct;
use App\Enums\CompanyType;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * About 200 wholesale products for the current marketplace's suppliers,
 * with tiered prices. Run by DemoSeeder inside the marketplace.
 */
class DemoCatalogueSeeder extends Seeder
{
    /**
     * Base items per category: [name, unit, moq, unit price in RWF].
     *
     * @var array<string, list<array{0: string, 1: string, 2: int, 3: int}>>
     */
    private const ITEMS = [
        'Food & Beverages' => [
            ['Maize flour', 'kg', 500, 750], ['Cassava flour', 'kg', 500, 680], ['Long-grain rice', 'kg', 1000, 1350],
            ['Red kidney beans', 'kg', 500, 1200], ['Sunflower cooking oil', 'litre', 200, 3200], ['Refined sugar', 'kg', 500, 1500],
            ['Bottled mineral water 500ml', 'carton', 50, 4800], ['Passion fruit juice concentrate', 'litre', 100, 2900],
            ['Roasted coffee beans', 'kg', 50, 14500], ['Black tea, loose leaf', 'kg', 100, 6800], ['Wheat flour', 'kg', 500, 980],
            ['Groundnut paste', 'kg', 100, 3600],
        ],
        'Agriculture' => [
            ['Hybrid maize seed', 'kg', 100, 2600], ['NPK 17-17-17 fertiliser', 'kg', 500, 950], ['Urea fertiliser', 'kg', 500, 870],
            ['Irish potato seed', 'kg', 1000, 520], ['Drip irrigation tape', 'metre', 1000, 180], ['Knapsack sprayer 16L', 'piece', 20, 38000],
            ['Poultry layer feed', 'kg', 500, 640], ['Shade net 50%', 'metre', 200, 1400], ['Hand hoe', 'piece', 50, 3800],
            ['Avocado seedlings (Hass)', 'piece', 100, 1500],
        ],
        'Construction Materials' => [
            ['Portland cement 50kg', 'piece', 100, 12500], ['Deformed steel bar 12mm', 'piece', 100, 11800], ['Iron sheets gauge 28', 'piece', 50, 9800],
            ['Clay roofing tiles', 'piece', 1000, 450], ['Wall paint, white, 20L', 'piece', 20, 42000], ['PVC water pipe 1"', 'metre', 200, 1600],
            ['Burnt clay bricks', 'piece', 5000, 120], ['Ceramic floor tiles 60x60', 'set', 50, 14500], ['Binding wire', 'kg', 100, 2400],
            ['Plywood 18mm', 'piece', 50, 36000],
        ],
        'Electronics' => [
            ['LED bulb 9W', 'piece', 200, 1500], ['Solar home system 50W', 'set', 10, 185000], ['Extension socket 4-way', 'piece', 100, 4800],
            ['Electric cable 2.5mm', 'metre', 500, 650], ['Smartphone charger USB-C', 'piece', 200, 3200], ['Rechargeable torch', 'piece', 100, 5500],
            ['Circuit breaker 32A', 'piece', 50, 6800], ['Ceiling fan 56"', 'piece', 20, 48000],
        ],
        'Apparel & Textiles' => [
            ['Cotton T-shirt, plain', 'piece', 200, 3800], ['School uniform shirt', 'piece', 300, 4500], ['Kitenge fabric', 'metre', 300, 2900],
            ['Work overalls', 'piece', 100, 14500], ['Cotton bed sheets', 'set', 50, 18000], ['Reusable face masks', 'piece', 1000, 450],
            ['Polo shirt, embroidered', 'piece', 100, 7200], ['Denim fabric', 'metre', 200, 4100],
        ],
        'Home & Kitchen' => [
            ['Stainless steel cooking pot set', 'set', 20, 38000], ['Plastic water tank 1000L', 'piece', 10, 165000], ['Charcoal stove (improved)', 'piece', 50, 9500],
            ['Plastic chairs', 'piece', 100, 7800], ['Dinner plates, ceramic', 'set', 50, 14500], ['Thermos flask 1.8L', 'piece', 50, 8200],
            ['Mosquito net, treated', 'piece', 200, 4600], ['Woven sisal basket', 'piece', 100, 6500],
        ],
        'Health & Beauty' => [
            ['Bar soap 200g', 'carton', 50, 21000], ['Hand sanitiser 500ml', 'piece', 200, 2800], ['Petroleum jelly 250g', 'piece', 200, 1900],
            ['Shea body lotion 400ml', 'piece', 100, 4500], ['Sanitary pads (pack of 10)', 'carton', 50, 32000], ['Toothpaste 100g', 'carton', 50, 36000],
        ],
        'Packaging' => [
            ['Kraft paper bags', 'piece', 5000, 120], ['PET bottles 500ml', 'piece', 5000, 95], ['Corrugated cartons', 'piece', 1000, 650],
            ['Woven polypropylene sacks 50kg', 'piece', 1000, 380], ['Stretch film', 'kg', 100, 3800], ['Glass jars 350ml', 'piece', 2000, 420],
            ['Food-grade containers 1L', 'piece', 1000, 350], ['Printed labels', 'piece', 10000, 25],
        ],
        'Industrial Equipment' => [
            ['Diesel generator 10kVA', 'piece', 2, 4800000], ['Grain milling machine', 'piece', 2, 2600000], ['Water pump 2HP', 'piece', 5, 380000],
            ['Welding machine 250A', 'piece', 5, 420000], ['Industrial weighing scale', 'piece', 5, 290000], ['Safety helmets', 'piece', 100, 6500],
        ],
        'Office & Stationery' => [
            ['A4 copy paper (ream)', 'carton', 20, 28000], ['Exercise books 96 pages', 'carton', 20, 24000], ['Ballpoint pens (box of 50)', 'carton', 20, 9500],
            ['Box files', 'piece', 100, 2400], ['Office desk', 'piece', 10, 145000], ['Whiteboard 120x90', 'piece', 10, 52000],
        ],
    ];

    /**
     * Which categories each demo supplier stocks.
     *
     * @var array<string, list<string>>
     */
    private const SUPPLIER_CATEGORIES = [
        'Rwanda Agro Supplies' => ['Food & Beverages', 'Agriculture'],
        'Nyabugogo Building Materials' => ['Construction Materials', 'Industrial Equipment'],
        'Thousand Hills Textiles' => ['Apparel & Textiles', 'Home & Kitchen'],
        'Lake Kivu Coffee Exporters' => ['Food & Beverages'],
        'Kigali Packaging Solutions' => ['Packaging', 'Office & Stationery'],
        'Musanze Electronics Hub' => ['Electronics', 'Health & Beauty'],
    ];

    /**
     * Each base item is listed in these grades by the suppliers that stock it.
     *
     * @var list<array{0: string, 1: float}>
     */
    private const GRADES = [['Standard', 1.0], ['Premium', 1.18]];

    public function __construct(
        private SaveProduct $saveProduct,
        private ActivatePlanManually $activatePlanManually,
    ) {}

    public function run(): void
    {
        $categories = Category::query()->whereNull('parent_id')->get()->keyBy('name');
        $recordedBy = User::query()->where('is_platform_admin', true)->firstOrFail();
        $growth = Plan::query()->forSellersOf($categories->first()->tenant_id)->where('name', 'Growth')->firstOrFail();

        foreach (self::SUPPLIER_CATEGORIES as $supplierName => $categoryNames) {
            $supplier = Company::query()->ofType(CompanyType::SUPPLIER)->where('name', $supplierName)->first();

            if (! $supplier || $supplier->products()->exists()) {
                continue;
            }

            // Enough listing room for the demo catalogue.
            if ($supplier->subscriptions()->doesntExist()) {
                $this->activatePlanManually->handle($supplier, $growth, 12, $recordedBy, "DEMO-GROWTH-{$supplier->id}");
            }

            $exportsInUsd = $supplierName === 'Lake Kivu Coffee Exporters';
            $count = 0;

            foreach ($categoryNames as $categoryName) {
                foreach (self::ITEMS[$categoryName] as [$name, $unit, $moq, $price]) {
                    foreach (self::GRADES as [$grade, $factor]) {
                        $this->createProduct($supplier, $categories[$categoryName]->id, "{$grade} {$name}", $unit, $moq, (int) round($price * $factor), $exportsInUsd, $count++);
                    }
                }
            }
        }
    }

    private function createProduct(Company $supplier, int $categoryId, string $name, string $unit, int $moq, int $rwfPrice, bool $inUsd, int $position): void
    {
        $currency = $inUsd ? 'USD' : 'RWF';
        // RWF to US cents at a fixed demo rate of 1 USD = 1,450 RWF.
        $basePrice = $inUsd ? max(5, (int) round($rwfPrice / 14.5)) : $rwfPrice;
        $round = fn (float $amount) => $inUsd ? max(1, (int) round($amount)) : max(10, (int) (round($amount / 10) * 10));

        $tiers = [
            ['min_qty' => $moq, 'unit_price' => $round($basePrice)],
            ['min_qty' => $moq * 5, 'unit_price' => $round($basePrice * 0.93)],
            ['min_qty' => $moq * 20, 'unit_price' => $round($basePrice * 0.86)],
        ];

        $product = $this->saveProduct->handle($supplier, null, [
            'category_id' => $categoryId,
            'name' => $name,
            'description' => "{$name} supplied by {$supplier->name}. Consistent quality, packed for transport, with delivery across Rwanda and the region.",
            'unit' => $unit,
            'moq' => $moq,
            'lead_time_min_days' => $inUsd ? 14 : 3 + ($position % 5),
            'lead_time_max_days' => $inUsd ? 30 : 10 + ($position % 10),
            'currency' => $currency,
            // USD listings stand for imports, from around the region and beyond.
            'origin_country' => $inUsd ? ['KE', 'UG', 'TZ', 'CN', 'AE'][$position % 5] : 'RW',
            'sample_available' => $position % 3 === 0,
            'sample_price' => $position % 3 === 0 ? $tiers[0]['unit_price'] * 2 : null,
        ], $tiers);

        // Most of the catalogue is listed; a few drafts and archived items for realism.
        $product->forceFill([
            'status' => match (true) {
                $position % 17 === 5 => ProductStatus::DRAFT,
                $position % 23 === 7 => ProductStatus::ARCHIVED,
                default => ProductStatus::ACTIVE,
            },
            'created_at' => now()->subDays(90 - ($position % 90)),
        ])->save();
    }
}
