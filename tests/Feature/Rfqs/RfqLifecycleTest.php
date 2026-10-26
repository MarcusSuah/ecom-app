<?php

use App\Actions\Quotes\SubmitQuote;
use App\Enums\QuoteStatus;
use App\Enums\RfqStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\User;
use App\Notifications\GuestRfqPosted;
use App\Notifications\QuoteDecided;
use App\Notifications\QuoteReceived;
use App\Notifications\RfqReceived;
use App\Services\Sms\SmsSender;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function rfqPayload(Category $category, array $overrides = []): array
{
    return [
        'title' => 'Maize flour, 25 kg bags',
        'category_id' => $category->id,
        'quantity' => 1000,
        'unit' => 'kg',
        'deliver_city' => 'Kigali',
        'needed_by' => now()->addMonth()->toDateString(),
        'notes' => 'Food grade bags.',
        'guest_phone' => '+250 788 123 456',
        ...$overrides,
    ];
}

it('sends a new request to matching suppliers by notification, email and SMS', function () {
    Notification::fake();
    $sms = $this->mock(SmsSender::class);
    $sms->shouldReceive('send')->once()->withArgs(fn (string $to, string $message) => $to === '+250788111222' && str_contains($message, 'Maize flour'));

    $category = Category::factory()->create();
    $supplier = Company::factory()->supplier()->verified()->create(['phone' => '+250788111222']);
    Product::factory()->for($supplier)->for($category)->active()->create();
    $buyer = userWithRole(Role::BUYER);

    $this->actingAs($buyer)->post(route('rfqs.store'), rfqPayload($category))->assertRedirect();

    $rfq = Rfq::sole();
    expect($rfq->buyer_id)->toBe($buyer->id)
        ->and($rfq->status)->toBe(RfqStatus::OPEN)
        ->and($rfq->expires_at->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and($rfq->recipients()->sole()->notified_at)->not->toBeNull();

    Notification::assertSentTo($supplier->owner, RfqReceived::class, fn (RfqReceived $notification, array $channels) => $channels === ['database', 'mail']);
});

it('lets a guest send a request without an account and follow it by link', function () {
    Notification::fake();
    $category = Category::factory()->create();
    $supplier = Company::factory()->supplier()->verified()->create();
    Product::factory()->for($supplier)->for($category)->active()->create();

    $this->post(route('rfqs.store'), rfqPayload($category, ['title' => 'Cement for a school', 'guest_name' => 'Aline', 'guest_email' => 'Aline@Example.rw']))
        ->assertSessionHasNoErrors();

    $rfq = Rfq::sole();
    expect($rfq)
        ->buyer_id->toBeNull()
        ->guest_email->toBe('aline@example.rw')
        ->access_token->toHaveLength(48)
        ->and($rfq->recipients()->sole()->company_id)->toBe($supplier->id);
    Notification::assertSentOnDemand(GuestRfqPosted::class, fn (GuestRfqPosted $notification, array $channels, object $notifiable) => $notifiable->routes['mail'] === ['aline@example.rw' => 'Aline']);

    $this->get(route('rfqs.track', $rfq->access_token))->assertInertia(fn (Assert $page) => $page
        ->component('catalog/rfq-track')
        ->where('rfq.title', 'Cement for a school')
        ->where('guest.claimed', false));
    $this->get(route('rfqs.track', str_repeat('x', 48)))->assertNotFound();
});

it('asks guests for a name and email instead of an account', function () {
    $this->post(route('rfqs.store'), rfqPayload(Category::factory()->create()))
        ->assertSessionHasErrors(['guest_name', 'guest_email']);

    expect(Rfq::count())->toBe(0);
});

it('moves a guest request into the account once its verified owner signs in', function () {
    $rfq = Rfq::factory()->create(['buyer_id' => null]);
    $rfq->forceFill(['guest_email' => 'aline@example.rw', 'access_token' => str_repeat('a', 48)])->save();
    $unverified = User::factory()->unverified()->create(['email' => 'aline@example.rw']);

    event(new Login('web', $unverified, false));
    expect($rfq->fresh()->buyer_id)->toBeNull();

    $unverified->forceFill(['email_verified_at' => now()])->save();
    event(new Login('web', $unverified, false));
    expect($rfq->fresh()->buyer_id)->toBe($unverified->id);
});

it('emails the buyer, or the guest, when a supplier quotes', function () {
    Notification::fake();
    Plan::factory()->sellerOf($this->tenant)->default()->create();
    $category = Category::factory()->create();
    $supplier = Company::factory()->supplier()->verified()->create();
    Product::factory()->for($supplier)->for($category)->active()->create();
    $this->post(route('rfqs.store'), rfqPayload($category, ['guest_name' => 'Aline', 'guest_email' => 'aline@example.rw']));
    $rfq = Rfq::sole();

    app(SubmitQuote::class)->handle($rfq, $supplier, [
        'currency' => 'RWF', 'lead_time_min_days' => 3, 'lead_time_max_days' => 7, 'valid_until' => now()->addWeek()->toDateString(),
    ], [['min_qty' => 1, 'unit_price' => 900]]);

    Notification::assertSentOnDemand(QuoteReceived::class, fn (QuoteReceived $notification, array $channels) => $channels === ['mail']);
});

it('pre-fills the form from the product quick form', function () {
    $product = Product::factory()->active()->create(['name' => 'Portland cement', 'moq' => 100]);

    $this->get(route('rfqs.create', ['product' => $product->id, 'quantity' => 250]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaults.title', 'Portland cement')
            ->where('defaults.category_id', (string) $product->category_id)
            ->where('defaults.quantity', '250')
            ->where('defaults.product_id', $product->id));
});

it('validates the request', function () {
    $this->actingAs(userWithRole(Role::BUYER))
        ->post(route('rfqs.store'), ['needed_by' => now()->subDay()->toDateString()])
        ->assertSessionHasErrors(['title' => 'Say what you need.', 'category_id', 'quantity', 'deliver_city', 'needed_by' => 'Choose a date after today.']);
});

it('rejects the other quotes when the buyer accepts one', function () {
    Notification::fake();
    $rfq = Rfq::factory()->create();
    [$winner, $other, $withdrawn] = [
        Quote::factory()->for($rfq)->create(),
        Quote::factory()->for($rfq)->create(),
        Quote::factory()->for($rfq)->status(QuoteStatus::WITHDRAWN)->create(),
    ];

    $this->actingAs($rfq->buyer)
        ->post(route('rfqs.quotes.accept', ['rfq' => $rfq, 'quote' => $winner]))
        ->assertSessionHasNoErrors();

    expect($rfq->fresh()->status)->toBe(RfqStatus::AWARDED)
        ->and($winner->fresh()->status)->toBe(QuoteStatus::ACCEPTED)
        ->and($other->fresh()->status)->toBe(QuoteStatus::REJECTED)
        ->and($withdrawn->fresh()->status)->toBe(QuoteStatus::WITHDRAWN);

    Notification::assertSentTo($winner->company->owner, QuoteDecided::class);
    Notification::assertSentTo($other->company->owner, QuoteDecided::class);
    Notification::assertNotSentTo($withdrawn->company->owner, QuoteDecided::class);
});

it('does not accept a second quote or one from another request', function () {
    $rfq = Rfq::factory()->status(RfqStatus::AWARDED)->create();
    $quote = Quote::factory()->for($rfq)->create();
    $open = Rfq::factory()->for($rfq->buyer, 'buyer')->create();
    $foreignQuote = Quote::factory()->create();

    $this->actingAs($rfq->buyer)
        ->post(route('rfqs.quotes.accept', ['rfq' => $rfq, 'quote' => $quote]))
        ->assertSessionHasErrors(['quote' => 'This request has already been decided.']);
    $this->actingAs($rfq->buyer)
        ->post(route('rfqs.quotes.accept', ['rfq' => $open, 'quote' => $foreignQuote]))
        ->assertSessionHasErrors(['quote' => 'This quote can no longer be accepted.']);
});

it('only lets the buyer see the comparison and decide', function () {
    $rfq = Rfq::factory()->create();
    $quote = Quote::factory()->for($rfq)->create();
    $someoneElse = User::factory()->create();

    $this->actingAs($someoneElse)->get(route('rfqs.show', $rfq))->assertForbidden();
    $this->actingAs($quote->company->owner)->post(route('rfqs.quotes.accept', ['rfq' => $rfq, 'quote' => $quote]))->assertForbidden();
    $this->actingAs($someoneElse)->post(route('rfqs.close', $rfq))->assertForbidden();

    expect($rfq->fresh()->status)->toBe(RfqStatus::OPEN);
});

it('compares quotes at the requested quantity, cheapest total first', function () {
    $rfq = Rfq::factory()->create(['quantity' => 1000]);
    $pricey = Quote::factory()->for($rfq)->tiers([[100, 1200], [500, 1100]])->create();
    $cheap = Quote::factory()->for($rfq)->tiers([[100, 1000], [1000, 900], [5000, 700]])->create();

    $this->actingAs($rfq->buyer)
        ->get(route('rfqs.show', $rfq))
        ->assertInertia(fn (Assert $page) => $page
            ->component('rfqs/show')
            ->where('quotes.0.id', $cheap->id)
            ->where('quotes.0.formatted_unit_price', 'RWF 900')
            ->where('quotes.0.formatted_total', 'RWF 900,000')
            ->where('quotes.0.supplier.is_verified', true)
            ->where('quotes.1.id', $pricey->id)
            ->where('quotes.1.formatted_total', 'RWF 1,100,000'));
});

it('lets the buyer close a request, declining its quotes', function () {
    $rfq = Rfq::factory()->create();
    $quote = Quote::factory()->for($rfq)->create();

    $this->actingAs($rfq->buyer)->post(route('rfqs.close', $rfq))->assertSessionHasNoErrors();

    expect($rfq->fresh()->status)->toBe(RfqStatus::CLOSED)
        ->and($quote->fresh()->status)->toBe(QuoteStatus::REJECTED);
});

it('expires open requests past their expiry, on every marketplace', function () {
    $expired = Rfq::factory()->expired()->create();
    $current = Rfq::factory()->create();
    $awarded = Rfq::factory()->expired()->status(RfqStatus::AWARDED)->create();

    $this->artisan('rfqs:expire')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(RfqStatus::EXPIRED)
        ->and($current->fresh()->status)->toBe(RfqStatus::OPEN)
        ->and($awarded->fresh()->status)->toBe(RfqStatus::AWARDED);
});

it('asks signed-in buyers for a phone number once and keeps it on their account', function () {
    Notification::fake();
    $buyer = userWithRole(Role::BUYER);
    $category = Category::factory()->create();

    $this->actingAs($buyer)->post(route('rfqs.store'), rfqPayload($category, ['guest_phone' => '']))->assertSessionHasErrors('guest_phone');
    $this->actingAs($buyer)->post(route('rfqs.store'), rfqPayload($category, ['guest_phone' => '0788 555 444']))->assertSessionHasNoErrors();

    expect($buyer->fresh()->phone)->toBe('0788 555 444');
});
