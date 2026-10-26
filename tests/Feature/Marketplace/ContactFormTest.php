<?php

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function contactPayload(array $overrides = []): array
{
    return [
        'name' => 'Jean Habimana',
        'email' => 'jean@example.rw',
        'phone' => '+250 788 123 456',
        'topic' => 'selling',
        'message' => 'Can we list our honey in USD for export buyers?',
        ...$overrides,
    ];
}

it('stores the message and tells this marketplace\'s admins', function () {
    Notification::fake();
    $admin = userWithRole(Role::ADMIN);
    $verifier = userWithRole(Role::VERIFIER);
    $otherAdmin = app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => userWithRole(Role::ADMIN));

    $this->post(route('contact.store'), contactPayload())->assertRedirect(route('contact.create'));

    $message = ContactMessage::sole();
    expect($message)
        ->tenant_id->toBe($this->tenant->id)
        ->topic->value->toBe('selling')
        ->user_id->toBeNull()
        ->handled_at->toBeNull();
    Notification::assertSentTo($admin, ContactMessageReceived::class, fn (ContactMessageReceived $notification) => $notification->toArray($admin)['tenant_id'] === $this->tenant->id);
    Notification::assertNotSentTo([$verifier, $otherAdmin], ContactMessageReceived::class);
});

it('links the message to a signed-in member', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('contact.store'), contactPayload());

    expect(ContactMessage::sole()->user_id)->toBe($member->id);
});

it('validates the form', function (array $overrides, string $field) {
    $this->post(route('contact.store'), contactPayload($overrides))->assertSessionHasErrors($field);

    expect(ContactMessage::count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'letters in the phone' => [['phone' => 'call me'], 'phone'],
    'unknown topic' => [['topic' => 'lottery'], 'topic'],
    'too short' => [['message' => 'Hi'], 'message'],
    'bot filled the honeypot' => [['website' => 'https://spam.example'], 'website'],
]);

it('limits how often one visitor can send messages', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post(route('contact.store'), contactPayload())->assertRedirect();
    }

    $this->post(route('contact.store'), contactPayload())->assertTooManyRequests();
    expect(ContactMessage::count())->toBe(3);
});

it('lets admins work through the inbox of their own marketplace', function () {
    $admin = userWithRole(Role::ADMIN);
    $open = ContactMessage::factory()->create();
    ContactMessage::factory()->handled()->create();
    app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => ContactMessage::factory()->create());

    $this->actingAs($admin)->get(route('admin.contact-messages.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Admin/ContactMessages/Index')
        ->has('messages.data', 1)
        ->where('messages.data.0.id', $open->id));

    $this->actingAs($admin)->post(route('admin.contact-messages.handled', $open))->assertSessionHasNoErrors();
    expect($open->fresh())->handled_at->not->toBeNull()->handled_by->toBe($admin->id);

    $this->actingAs(userWithRole(Role::BUYER))->get(route('admin.contact-messages.index'))->assertForbidden();
});

it('renders the contact page with the member\'s details filled in', function () {
    $member = User::factory()->create(['name' => 'Bea Buyer']);

    $this->actingAs($member)->get(route('contact.create', ['topic' => 'payments']))->assertInertia(fn (Assert $page) => $page
        ->component('content/contact')
        ->where('defaults', ['name' => 'Bea Buyer', 'email' => $member->email, 'topic' => 'payments'])
        ->has('topics', 6));
});
