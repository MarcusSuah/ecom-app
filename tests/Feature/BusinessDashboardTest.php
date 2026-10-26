<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a seller their revenue trend, orders to fulfil and payouts', function () {
    $owner = User::factory()->create();
    $shop = Company::factory()->for($owner, 'owner')->shop()->create();
    Order::factory()->for($shop, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 10000, 'placed_at' => now()->subDays(2)]);
    Order::factory()->for($shop, 'seller')->status(OrderStatus::PAID)->create(['total' => 5000, 'placed_at' => now()->subDay()]);
    Order::factory()->for($shop, 'seller')->status(OrderStatus::PENDING_PAYMENT)->create(['total' => 99000, 'placed_at' => now()]);
    // Last period, for the comparison.
    Order::factory()->for($shop, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 7500, 'placed_at' => now()->subDays(40)]);
    // Another seller's order does not count.
    Order::factory()->status(OrderStatus::COMPLETED)->create(['total' => 123456, 'placed_at' => now()]);

    $this->actingAs($owner)->get(route('dashboard', ['range' => 30]))->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('range', 30)
        ->where('seller.kpis.revenue_formatted', 'RWF 15,000')
        ->where('seller.kpis.orders', 2)
        ->where('seller.kpis.revenue_change', 100)
        ->where('seller.kpis.to_fulfil', 1)
        ->where('seller.kpis.awaiting_payment', 1)
        ->has('seller.trend.points', 30)
        ->has('seller.recent_orders', 3));
});

it('shows a buyer what they spent and what is on its way', function () {
    $buyer = User::factory()->create();
    Order::factory()->for($buyer, 'buyer')->status(OrderStatus::SHIPPED)->create(['total' => 4000, 'placed_at' => now()]);
    Order::factory()->for($buyer, 'buyer')->status(OrderStatus::PENDING_PAYMENT)->create(['placed_at' => now()]);

    $this->actingAs($buyer)->get(route('dashboard', ['range' => 7]))->assertInertia(fn (Assert $page) => $page
        ->where('seller', null)
        ->where('buyer.kpis.spent', 'RWF 4,000')
        ->where('buyer.kpis.on_the_way', 1)
        ->where('buyer.kpis.awaiting_payment', 1)
        ->has('buyer.trend.points', 7));
});

it('groups a twelve-month view by month', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard', ['range' => 365]))->assertInertia(fn (Assert $page) => $page
        ->where('range', 365)
        ->has('buyer.trend.points', fn ($points) => $points->etc())
        ->where('buyer.trend.points.0.date', now()->subDays(364)->format('Y-m')));
});

it('shows marketplace admins sales, commission and pending payouts', function () {
    Order::factory()->status(OrderStatus::COMPLETED)->create(['total' => 20000, 'placed_at' => now()]);
    $payout = new Payout;
    $order = Order::sole();
    $payout->forceFill(['tenant_id' => $this->tenant->id, 'company_id' => $order->seller_company_id, 'order_id' => $order->id, 'gross' => 20000, 'commission_rate' => 5, 'commission' => 1000, 'net' => 19000, 'currency' => 'RWF', 'status' => 'pending'])->save();

    $this->actingAs(userWithRole(Role::ADMIN))->get(route('admin.dashboard', ['tab' => 'payouts']))->assertInertia(fn (Assert $page) => $page
        ->where('kpis.gmv', 'RWF 20,000')
        ->where('kpis.commission', 'RWF 1,000')
        ->where('kpis.pending_payouts', 'RWF 19,000')
        ->where('counts.payouts', 1)
        ->has('table.rows', 1));
});
