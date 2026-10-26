<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateChange;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('keeps a history of every rate change with who made it', function () {
    CurrencyRate::factory()->rate('1450')->create();
    $admin = userWithRole(Role::ADMIN);

    $this->actingAs($admin)->put(route('admin.currency.update'), ['quote' => 'RWF', 'rate' => '1500'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('admin.currency.update'), ['quote' => 'RWF', 'rate' => '1520']);
    $this->actingAs($admin)->delete(route('admin.currency.destroy', 'RWF'));

    $changes = CurrencyRateChange::query()->orderBy('id')->get();
    expect($changes->map(fn (CurrencyRateChange $change) => [$change->source, $change->old_rate === null ? null : (float) $change->old_rate, $change->new_rate === null ? null : (float) $change->new_rate])->all())->toBe([
        ['fixed', null, 1450.0],
        ['manual', null, 1500.0],
        ['manual', 1500.0, 1520.0],
        ['reset', 1520.0, null],
    ])->and($changes[1]->changed_by)->toBe($admin->id);

    $this->actingAs($admin)->get(route('admin.currency.index'))->assertInertia(fn (Assert $page) => $page
        ->has('history', 4)
        ->where('history.0.source', 'reset'));
});

it('shows a seller report in USD with the rate used', function () {
    CurrencyRate::factory()->rate('1450')->create();
    $owner = User::factory()->create();
    $shop = Company::factory()->for($owner, 'owner')->shop()->create();
    Order::factory()->for($shop, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 145000, 'currency' => 'RWF']);
    Order::factory()->for($shop, 'seller')->status(OrderStatus::COMPLETED)->create(['total' => 1000, 'currency' => 'USD']);

    $this->actingAs($owner)->get(route('seller.reports.index', ['currency' => 'USD']))->assertInertia(fn (Assert $page) => $page
        ->has('totals', 1)
        ->where('totals.0.currency', 'USD')
        ->where('totals.0.converted', true)
        ->where('totals.0.gross', 'USD 110.00')
        ->where('rates.0.label', '1 USD = 1,450 RWF'));

    $csv = $this->actingAs($owner)->get(route('seller.reports.export', ['type' => 'orders', 'currency' => 'USD']))->streamedContent();
    expect($csv)->toContain('Total (USD)')->toContain('1 USD = 1,450 RWF');
});
