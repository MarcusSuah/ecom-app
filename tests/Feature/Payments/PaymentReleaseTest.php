<?php

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlanLimit;
use App\Enums\Role;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * An order with a held payment, at the given status.
 */
function heldOrder(OrderStatus $status = OrderStatus::DELIVERED, int $total = 100000, array $attributes = []): Order
{
    $order = Order::factory()->status($status)->create(['total' => $total, 'subtotal' => $total, ...$attributes]);
    Payment::factory()->forOrder($order)->create();

    return $order;
}

function openDispute(Order $order): Dispute
{
    test()->actingAs($order->buyer)->post(route('orders.dispute', $order), ['reason' => 'damaged', 'details' => 'The box arrived crushed.'])->assertSessionHasNoErrors();

    return $order->dispute()->sole();
}

it('releases the payment when the buyer confirms receipt, net of commission', function () {
    Plan::factory()->sellerOf($this->tenant)->default()->limit(PlanLimit::COMMISSION_PERCENT, 5)->create();
    $order = heldOrder(total: 123457);

    $this->actingAs($order->buyer)->post(route('orders.confirm-receipt', $order))->assertSessionHasNoErrors();

    $payout = Payout::sole();
    expect($order->fresh()->status)->toBe(OrderStatus::COMPLETED)
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::RELEASED)
        ->and($payout->company_id)->toBe($order->seller_company_id)
        ->and($payout->gross)->toBe(123457)
        ->and((float) $payout->commission_rate)->toBe(5.0)
        ->and($payout->commission)->toBe(6173)
        ->and($payout->net)->toBe(117284)
        ->and($payout->status)->toBe(PayoutStatus::PENDING);
});

it('uses the platform default commission when the seller plan sets none', function () {
    config(['payments.default_commission_percent' => 7.5]);
    Plan::factory()->sellerOf($this->tenant)->default()->create();
    $order = heldOrder(total: 10000);

    $this->actingAs($order->buyer)->post(route('orders.confirm-receipt', $order));

    expect(Payout::sole())->commission->toBe(750)->net->toBe(9250);
});

it('marks a shipped order delivered when the buyer confirms receipt first', function () {
    $order = heldOrder(OrderStatus::SHIPPED, attributes: ['type' => 'wholesale', 'fulfilment' => 'delivery']);

    $this->actingAs($order->buyer)->post(route('orders.confirm-receipt', $order))->assertSessionHasNoErrors();

    expect($order->fresh())->status->toBe(OrderStatus::COMPLETED)->delivered_at->not->toBeNull();
});

it('does not release on anything else', function (OrderStatus $status) {
    $order = heldOrder($status);

    $this->actingAs($order->buyer)->post(route('orders.confirm-receipt', $order))->assertSessionHasErrors('order');
    $this->actingAs($order->seller->owner)->post(route('orders.confirm-receipt', $order))->assertForbidden();

    expect($order->payments()->sole()->status)->toBe(PaymentStatus::HELD)->and(Payout::count())->toBe(0);
})->with([
    'still being prepared' => [OrderStatus::PREPARING],
    'just paid' => [OrderStatus::PAID],
]);

it('auto-releases delivered orders after the configured days', function () {
    config(['payments.auto_release_days' => 3]);
    $due = heldOrder(attributes: ['delivered_at' => now()->subDays(3)->subMinute()]);
    $notYet = heldOrder(attributes: ['delivered_at' => now()->subDays(2)]);

    $this->artisan('payments:release-due')->assertSuccessful();

    expect($due->fresh()->status)->toBe(OrderStatus::COMPLETED)
        ->and($notYet->fresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and(Payout::sole()->order_id)->toBe($due->id);
});

it('auto-releases on every marketplace at that marketplace\'s commission', function () {
    $other = Tenant::factory()->create();
    $order = app(CurrentTenant::class)->runAs($other, function () use ($other) {
        Plan::factory()->sellerOf($other)->default()->limit(PlanLimit::COMMISSION_PERCENT, 10)->create();

        return heldOrder(total: 50000, attributes: ['delivered_at' => now()->subWeek()]);
    });
    app(CurrentTenant::class)->set(null);

    $this->artisan('payments:release-due')->assertSuccessful();

    expect(Payout::withoutGlobalScopes()->sole())->tenant_id->toBe($other->id)->commission->toBe(5000);
});

it('does not auto-release an order with an open dispute', function () {
    config(['payments.auto_release_days' => 3]);
    $order = heldOrder(attributes: ['delivered_at' => now()->subWeek()]);
    openDispute($order);

    $this->artisan('payments:release-due')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::HELD);
});

it('blocks the buyer\'s own release while a dispute is open', function () {
    $order = heldOrder();
    openDispute($order);

    $this->actingAs($order->buyer)->post(route('orders.confirm-receipt', $order))->assertSessionHasErrors('order');

    expect(Payout::count())->toBe(0);
});

it('lets the buyer open one dispute, only while the payment is held', function () {
    $order = heldOrder();
    openDispute($order);

    $this->actingAs($order->buyer)->post(route('orders.dispute', $order), ['reason' => 'other', 'details' => 'Another complaint here.'])
        ->assertSessionHasErrors(['reason' => 'A dispute has already been opened for this order.']);

    $unpaid = Order::factory()->status(OrderStatus::PENDING_PAYMENT)->create();
    $this->actingAs($unpaid->buyer)->post(route('orders.dispute', $unpaid), ['reason' => 'other', 'details' => 'Nothing was paid yet.'])
        ->assertSessionHasErrors(['reason' => 'Disputes can only be opened while the payment is held.']);
});

it('lets an admin resolve a dispute by releasing to the seller', function () {
    $order = heldOrder(OrderStatus::OUT_FOR_DELIVERY, attributes: ['fulfilment' => 'delivery']);
    $dispute = openDispute($order);

    $this->actingAs(userWithRole(Role::ADMIN))
        ->post(route('admin.disputes.resolve', $dispute), ['outcome' => 'resolved_release', 'note' => 'Delivery photo confirms it arrived.'])
        ->assertRedirect(route('admin.disputes.index'));

    expect($dispute->fresh()->status)->toBe(DisputeStatus::RESOLVED_RELEASE)
        ->and($order->fresh()->status)->toBe(OrderStatus::COMPLETED)
        ->and(Payout::sole()->order_id)->toBe($order->id);
});

it('lets an admin resolve a dispute by refunding the buyer', function () {
    $order = heldOrder();
    $dispute = openDispute($order);

    $this->actingAs(userWithRole(Role::ADMIN))
        ->post(route('admin.disputes.resolve', $dispute), ['outcome' => 'resolved_refund', 'note' => 'Goods were damaged.']);

    expect($dispute->fresh()->status)->toBe(DisputeStatus::RESOLVED_REFUND)
        ->and($order->fresh()->status)->toBe(OrderStatus::REFUNDED)
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::REFUNDED)
        ->and(Payout::count())->toBe(0);
});

it('lets only dispute resolvers decide', function () {
    $dispute = openDispute(heldOrder());

    $this->actingAs(userWithRole(Role::VERIFIER))
        ->post(route('admin.disputes.resolve', $dispute), ['outcome' => 'resolved_refund', 'note' => 'x'])
        ->assertForbidden();
});

it('lets the seller cancel a paid order with a refund', function () {
    $order = heldOrder(OrderStatus::ACCEPTED);

    $this->actingAs($order->seller->owner)->post(route('seller.orders.refund', $order), ['reason' => 'Out of stock'])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::REFUNDED)
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::REFUNDED);
});

it('records payouts as paid by an admin', function () {
    $order = heldOrder();
    $this->actingAs($order->buyer)->post(route('orders.confirm-receipt', $order));
    $payout = Payout::sole();

    $this->actingAs(User::factory()->create())->post(route('admin.payouts.paid', $payout), ['reference' => 'BK-1'])->assertForbidden();
    $this->actingAs(userWithRole(Role::ADMIN))->post(route('admin.payouts.paid', $payout), ['reference' => 'BK-77'])->assertSessionHasNoErrors();

    expect($payout->fresh())->status->toBe(PayoutStatus::PAID)->reference->toBe('BK-77')->paid_at->not->toBeNull();
});

it('keeps photos the buyer adds as proof, for the buyer, the seller and dispute staff only', function () {
    Storage::fake('local');
    $order = heldOrder();

    $this->actingAs($order->buyer)->post(route('orders.dispute', $order), [
        'reason' => 'damaged',
        'details' => 'The box arrived crushed.',
        'photos' => [UploadedFile::fake()->image('box.jpg', 1200, 900), UploadedFile::fake()->image('label.jpg', 800, 600), UploadedFile::fake()->image('item.jpg', 800, 600)],
    ])->assertSessionHasNoErrors();

    $dispute = $order->dispute()->sole();
    expect($dispute->photos)->toHaveCount(3);
    Storage::disk('local')->assertExists($dispute->photos);

    $this->actingAs($order->buyer)->get(route('disputes.photos.show', [$dispute, 0]))->assertOk();
    $this->actingAs($order->seller->owner)->get(route('disputes.photos.show', [$dispute, 1]))->assertOk();
    $this->actingAs(userWithRole(Role::ADMIN))->get(route('disputes.photos.show', [$dispute, 2]))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('disputes.photos.show', [$dispute, 0]))->assertForbidden();
    $this->actingAs($order->buyer)->get(route('disputes.photos.show', [$dispute, 9]))->assertNotFound();
});

it('refuses more than six proof photos', function () {
    $order = heldOrder();

    $this->actingAs($order->buyer)->post(route('orders.dispute', $order), [
        'reason' => 'damaged',
        'details' => 'The box arrived crushed.',
        'photos' => array_map(fn (int $i) => UploadedFile::fake()->image("{$i}.jpg", 300, 300), range(1, 7)),
    ])->assertSessionHasErrors(['photos' => 'Add at most 6 photos.']);
});
