<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Notifications\OrderStatusChanged;

it('gives the buyer, the seller and reporting staff the order PDF', function (string $viewer) {
    $order = Order::factory()->status(OrderStatus::PAID)->create(['number' => 'ORD-PDF-1']);
    $user = match ($viewer) {
        'buyer' => $order->buyer,
        'seller' => $order->seller->owner,
        'marketplace admin' => userWithRole(Role::ADMIN),
    };

    $this->actingAs($user)->get(route('orders.document', $order))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('ORD-PDF-1.pdf');
})->with(['buyer', 'seller', 'marketplace admin']);

it('keeps the order PDF from anyone else', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('orders.document', $order))->assertForbidden();
});

it('lets a seller download their plan invoice, and nobody else', function () {
    $owner = User::factory()->create();
    $company = Company::factory()->for($owner, 'owner')->supplier()->create();
    $subscription = Subscription::factory()->forSubscriber($company, Plan::factory()->create())->create(['price' => 15000]);
    $invoice = SubscriptionInvoice::factory()->forSubscription($subscription)->create(['number' => 'INV-SELLER-1']);

    $this->actingAs($owner)->get(route('billing.invoices.pdf', $invoice))->assertOk()->assertDownload('INV-SELLER-1.pdf');
    $this->actingAs(User::factory()->create())->get(route('billing.invoices.pdf', $invoice))->assertForbidden();
});

it('lets billing staff download the marketplace\'s platform invoice', function () {
    $invoice = SubscriptionInvoice::factory()->forSubscription(Subscription::sole())->create(['number' => 'INV-PLATFORM-1']);

    $this->actingAs(userWithRole(Role::ADMIN))->get(route('billing.invoices.pdf', $invoice))->assertOk()->assertDownload('INV-PLATFORM-1.pdf');
    $this->actingAs(userWithRole(Role::SUPPLIER))->get(route('billing.invoices.pdf', $invoice))->assertForbidden();
});

it('gives a seller their payout statement for a period', function () {
    [$owner, $company] = supplierOwner();

    $this->actingAs($owner)->get(route('seller.payouts.statement', ['from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertOk()
        ->assertDownload("payouts-{$company->slug}-2026-09-01_2026-09-30.pdf");
});

it('attaches the receipt to the buyer\'s payment email only', function () {
    $order = Order::factory()->status(OrderStatus::PAID)->create(['number' => 'ORD-RCPT-1']);
    $notification = new OrderStatusChanged($order);

    expect($notification->toMail($order->buyer)->rawAttachments)->toHaveCount(1)
        ->and($notification->toMail($order->buyer)->rawAttachments[0]['name'])->toBe('ORD-RCPT-1.pdf')
        ->and($notification->toMail($order->seller->owner)->rawAttachments)->toBeEmpty();

    $order->forceFill(['status' => OrderStatus::SHIPPED])->save();

    expect((new OrderStatusChanged($order))->toMail($order->buyer)->rawAttachments)->toBeEmpty();
});
