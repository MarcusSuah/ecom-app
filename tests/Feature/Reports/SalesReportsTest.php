<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A released payout for the order at 5% commission.
 */
function payoutFor(Order $order, string $status = 'pending'): Payout
{
    $commission = intdiv($order->total, 20);
    $payout = new Payout;
    $payout->forceFill([
        'tenant_id' => $order->tenant_id,
        'company_id' => $order->seller_company_id,
        'order_id' => $order->id,
        'gross' => $order->total,
        'commission_rate' => 5,
        'commission' => $commission,
        'net' => $order->total - $commission,
        'currency' => $order->currency,
        'status' => $status,
        'paid_at' => $status === 'paid' ? now() : null,
    ])->save();

    return $payout;
}

it('totals a seller\'s sales, refunds, commission and payouts for the chosen period only', function () {
    $owner = User::factory()->create();
    $shop = Company::factory()->for($owner, 'owner')->shop()->create();
    payoutFor(Order::factory()->for($shop, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 20000, 'placed_at' => '2026-09-10 10:00']), 'paid');
    payoutFor(Order::factory()->for($shop, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 10000, 'placed_at' => '2026-09-20 10:00']));
    Order::factory()->for($shop, 'seller')->status(OrderStatus::REFUNDED)->create(['total' => 4000, 'placed_at' => '2026-09-15 10:00']);
    Order::factory()->for($shop, 'seller')->status(OrderStatus::PENDING_PAYMENT)->create(['total' => 99000, 'placed_at' => '2026-09-15 10:00']);
    // Outside the period, and another seller's sale.
    Order::factory()->for($shop, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 50000, 'placed_at' => '2026-10-01 10:00']);
    Order::factory()->status(OrderStatus::COMPLETED)->create(['total' => 70000, 'placed_at' => '2026-09-12 10:00']);

    $this->actingAs($owner)->get(route('seller.reports.index', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertInertia(fn (Assert $page) => $page
        ->component('seller/reports')
        ->where('period', ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->has('totals', 1)
        ->where('totals.0.orders', 2)
        ->where('totals.0.gross', 'RWF 30,000')
        ->where('totals.0.average', 'RWF 15,000')
        ->where('totals.0.refunds', 1)
        ->where('totals.0.refunded', 'RWF 4,000')
        ->where('totals.0.commission', 'RWF 1,500')
        ->where('totals.0.net', 'RWF 28,500')
        ->where('totals.0.paid_out', 'RWF 19,000')
        ->where('totals.0.pending', 'RWF 9,500')
        ->has('recentOrders', 4));
});

it('keeps each currency separate', function () {
    $owner = User::factory()->create();
    $shop = Company::factory()->for($owner, 'owner')->shop()->create();
    Order::factory()->for($shop, 'seller')->create(['total' => 20000, 'currency' => 'RWF']);
    Order::factory()->for($shop, 'seller')->create(['total' => 1500, 'currency' => 'USD']);

    $this->actingAs($owner)->get(route('seller.reports.index'))->assertInertia(fn (Assert $page) => $page
        ->has('totals', 2)
        ->where('totals.0.currency', 'RWF')
        ->where('totals.1.currency', 'USD'));
});

it('only lets people with a company open seller reports', function () {
    $this->actingAs(User::factory()->create())->get(route('seller.reports.index'))->assertForbidden();
});

it('downloads a seller\'s orders as CSV, guarding against formulas', function () {
    $owner = User::factory()->create();
    $shop = Company::factory()->for($owner, 'owner')->shop()->create();
    $buyer = User::factory()->create(['name' => '=HYPERLINK("http://evil.test")']);
    Order::factory()->for($shop, 'seller')->for($buyer, 'buyer')->create(['number' => 'ORD-MINE', 'total' => 12500, 'placed_at' => '2026-09-10 10:00']);
    Order::factory()->create(['number' => 'ORD-THEIRS', 'placed_at' => '2026-09-10 10:00']);

    $response = $this->actingAs($owner)->get(route('seller.reports.export', ['type' => 'orders', 'from' => '2026-09-01', 'to' => '2026-09-30']));

    $response->assertOk()->assertDownload("{$shop->slug}-orders-2026-09-01_2026-09-30.csv");
    $lines = array_map(str_getcsv(...), explode("\n", trim(ltrim($response->streamedContent(), "\u{FEFF}"))));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toBe(['Order', 'Placed', 'Status', 'Type', 'Fulfilment', 'Buyer', 'Buyer email', 'Subtotal', 'Delivery', 'Total', 'Currency', 'Payment'])
        ->and($lines[1][0])->toBe('ORD-MINE')
        ->and($lines[1][5])->toBe('\'=HYPERLINK("http://evil.test")')
        ->and($lines[1][9])->toBe('12500');
});

it('rejects unknown export types', function () {
    [$owner] = supplierOwner();

    $this->actingAs($owner)->get('/seller/reports/secrets.csv')->assertNotFound();
});

it('shows marketplace staff the marketplace\'s totals and top sellers, not other marketplaces\'', function () {
    $order = Order::factory()->status(OrderStatus::COMPLETED)->create(['total' => 20000]);
    payoutFor($order);
    app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Order::factory()->status(OrderStatus::COMPLETED)->create(['total' => 90000]));

    $this->actingAs(userWithRole(Role::ADMIN))->get(route('admin.reports.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Reports/Index')
        ->where('totals.0.gross', 'RWF 20,000')
        ->where('totals.0.commission', 'RWF 1,000')
        ->has('sellers', 1)
        ->where('sellers.0.seller', $order->seller->name)
        ->where('sellers.0.net_formatted', 'RWF 19,000'));
});

it('limits marketplace reports to staff with reports.view', function () {
    $this->actingAs(userWithRole(Role::SUPPLIER))->get(route('admin.reports.index'))->assertForbidden();
    $this->actingAs(userWithRole(Role::SUPPLIER))->get(route('admin.reports.export', 'orders'))->assertForbidden();
});

it('downloads sales by seller as CSV for marketplace staff', function () {
    $order = Order::factory()->status(OrderStatus::COMPLETED)->create(['total' => 20000]);
    payoutFor($order);

    $response = $this->actingAs(userWithRole(Role::ADMIN))->get(route('admin.reports.export', 'sellers'));

    $response->assertOk();
    expect($response->streamedContent())->toContain($order->seller->name);
});

it('shows platform admins every marketplace side by side', function () {
    $platformAdmin = User::factory()->create();
    $platformAdmin->forceFill(['is_platform_admin' => true])->save();
    Order::factory()->status(OrderStatus::COMPLETED)->create(['total' => 1500, 'currency' => 'USD']);
    app(CurrentTenant::class)->runAs(Tenant::factory()->create(['name' => 'Second market']), fn () => Order::factory()->status(OrderStatus::COMPLETED)->create(['total' => 2500, 'currency' => 'USD']));

    $this->actingAs($platformAdmin)->get(route('platform.analytics.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Platform/Analytics')
        ->has('marketplaces', 2)
        ->where('totals.marketplaces', 2)
        ->where('totals.orders', 2)
        ->where('totals.gmv', 'USD 40.00'));

    $this->actingAs($platformAdmin)->get(route('platform.analytics.export'))->assertOk()->assertDownload();
});

it('keeps platform analytics from marketplace admins', function () {
    $this->actingAs(userWithRole(Role::ADMIN))->get(route('platform.analytics.index'))->assertForbidden();
});

it('breaks platform analytics down by business and store, with expenses and VAT', function () {
    $platformAdmin = User::factory()->create();
    $platformAdmin->forceFill(['is_platform_admin' => true])->save();
    $supplier = Company::factory()->supplier()->create(['name' => 'Rift Steel']);
    $shop = Company::factory()->shop()->create(['name' => 'Inzozi Bakery']);
    payoutFor(Order::factory()->for($supplier, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 11800, 'currency' => 'USD']));
    Order::factory()->for($shop, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 2360, 'currency' => 'USD']);

    $this->actingAs($platformAdmin)->get(route('platform.analytics.index', ['level' => 'businesses']))->assertInertia(fn (Assert $page) => $page
        ->where('level', 'businesses')
        ->has('sellers', 1)
        ->where('sellers.0.name', 'Rift Steel')
        ->where('sellers.0.revenue', 'USD 118.00')
        ->where('sellers.0.expenses', 'USD 5.90')
        ->where('sellers.0.net', 'USD 112.10')
        ->where('sellers.0.vat', 'USD 18.00')
        ->where('marketplaces.0.vat', 'USD 21.60'));

    $this->actingAs($platformAdmin)->get(route('platform.analytics.index', ['level' => 'stores']))->assertInertia(fn (Assert $page) => $page
        ->has('sellers', 1)
        ->where('sellers.0.name', 'Inzozi Bakery')
        ->where('sellers.0.type_label', 'Store'));

    $csv = $this->actingAs($platformAdmin)->get(route('platform.analytics.export', ['level' => 'stores']))->streamedContent();
    expect($csv)->toContain('Inzozi Bakery')->toContain('VAT in sales (USD)');
});

it('shows sellers the VAT included in their sales at the marketplace rate', function () {
    $owner = User::factory()->create();
    $shop = Company::factory()->for($owner, 'owner')->shop()->create();
    Order::factory()->for($shop, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 11800]);

    $this->actingAs($owner)->get(route('seller.reports.index'))->assertInertia(fn (Assert $page) => $page
        ->where('totals.0.vat', 'RWF 1,800')
        ->where('totals.0.vat_percent', 18));
});
