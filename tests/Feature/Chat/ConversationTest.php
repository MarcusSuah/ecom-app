<?php

use App\Enums\Role;
use App\Events\MessageSent;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('opens one conversation per member, company and topic', function () {
    $member = userWithRole(Role::BUYER);
    $product = Product::factory()->active()->create();
    $company = $product->company;

    $this->actingAs($member)->post(route('conversations.store'), ['company' => $company->slug, 'product_id' => $product->id])->assertRedirect();
    $this->actingAs($member)->post(route('conversations.store'), ['company' => $company->slug, 'product_id' => $product->id]);
    $this->actingAs($member)->post(route('conversations.store'), ['company' => $company->slug]);

    expect(Conversation::count())->toBe(2)
        ->and(Conversation::query()->where('product_id', $product->id)->sole()->participants->modelKeys())
        ->toEqualCanonicalizing([$member->id, $company->owner_id]);
});

it('does not let a company owner chat with their own company', function () {
    $company = Company::factory()->supplier()->create();

    $this->actingAs($company->owner)
        ->post(route('conversations.store'), ['company' => $company->slug])
        ->assertSessionHasErrors(['company' => 'You cannot start a chat with your own company.']);
});

it('only lets the buyer open a chat about their request, with a supplier it was sent to', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $rfq = Rfq::factory()->sentTo($supplier)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('conversations.store'), ['company' => $supplier->slug, 'rfq_id' => $rfq->id])
        ->assertForbidden();
    $this->actingAs($rfq->buyer)
        ->post(route('conversations.store'), ['company' => Company::factory()->supplier()->create()->slug, 'rfq_id' => $rfq->id])
        ->assertForbidden();
    $this->actingAs($rfq->buyer)
        ->post(route('conversations.store'), ['company' => $supplier->slug, 'rfq_id' => $rfq->id])
        ->assertRedirect();
});

it('only lets participants read a conversation', function () {
    $member = User::factory()->create();
    $company = Company::factory()->create();
    $conversation = Conversation::factory()->between($member, $company)->create();

    $this->actingAs($member)->get(route('conversations.show', $conversation))->assertOk();
    $this->actingAs($company->owner)->get(route('conversations.show', $conversation))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('conversations.show', $conversation))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('conversations.messages.store', $conversation), ['body' => 'Hi'])->assertForbidden();
});

it('hides other marketplaces\' conversations', function () {
    $member = User::factory()->create();
    $foreign = app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Conversation::factory()->between($member, Company::factory()->create())->create());

    $this->actingAs($member)->get(route('conversations.show', $foreign))->assertNotFound();
});

it('sends messages with attachments and broadcasts them', function () {
    Storage::fake('local');
    Event::fake([MessageSent::class]);
    $member = User::factory()->create();
    $conversation = Conversation::factory()->between($member, Company::factory()->create())->create();

    $this->actingAs($member)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Spec attached', 'attachment' => UploadedFile::fake()->create('spec.pdf', 200, 'application/pdf')])
        ->assertSessionHasNoErrors();

    $message = Message::sole();
    expect($message->body)->toBe('Spec attached')->and($message->attachment_name)->toBe('spec.pdf');
    Storage::disk('local')->assertExists($message->attachment_path);
    Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->broadcastOn()[0]->name === "private-conversations.{$conversation->id}");
});

it('rejects empty messages and unsupported attachments', function () {
    Storage::fake('local');
    $member = User::factory()->create();
    $conversation = Conversation::factory()->between($member, Company::factory()->create())->create();

    $this->actingAs($member)->post(route('conversations.messages.store', $conversation), ['body' => '  '])
        ->assertSessionHasErrors(['body' => 'Write a message or attach a file.']);
    $this->actingAs($member)->post(route('conversations.messages.store', $conversation), ['attachment' => UploadedFile::fake()->create('run.exe', 10, 'application/octet-stream')])
        ->assertSessionHasErrors(['attachment' => 'Attach an image (JPG, PNG, WebP) or a PDF.']);
});

it('serves attachments to participants only', function () {
    Storage::fake('local');
    $member = User::factory()->create();
    $conversation = Conversation::factory()->between($member, Company::factory()->create())->create();
    $this->actingAs($member)->post(route('conversations.messages.store', $conversation), ['attachment' => UploadedFile::fake()->image('photo.jpg')]);
    $message = Message::sole();

    $this->actingAs($member)->get(route('messages.attachment', $message))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('messages.attachment', $message))->assertForbidden();
});

it('counts unread messages and clears them when the conversation is opened', function () {
    $member = User::factory()->create();
    $company = Company::factory()->create();
    $conversation = Conversation::factory()->between($member, $company)->create();
    $this->actingAs($company->owner)->post(route('conversations.messages.store', $conversation), ['body' => 'Hello']);
    $this->actingAs($company->owner)->post(route('conversations.messages.store', $conversation), ['body' => 'Are you there?']);

    $this->actingAs($member)->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('unreadMessagesCount', 2)->where('conversations.data.0.unread_count', 2));

    $this->actingAs($member)->get(route('conversations.show', $conversation))
        ->assertInertia(fn (Assert $page) => $page->component('messages/show')->has('messages', 2)->where('messages.0.mine', false));

    $this->actingAs($member)->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('unreadMessagesCount', 0));
    $this->actingAs($company->owner)->get(route('conversations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('unreadMessagesCount', 0));
});

it('opens the chat dialog with the seller\'s details and its messages as JSON', function () {
    $member = userWithRole(Role::BUYER);
    $product = Product::factory()->active()->create();
    $company = $product->company;
    $company->forceFill(['city' => 'Kigali', 'phone' => '+250 788 000 111'])->save();

    $response = $this->actingAs($member)->postJson(route('conversations.open'), ['company' => $company->slug, 'product_id' => $product->id]);

    $response->assertOk()
        ->assertJsonPath('counterpart.name', $company->name)
        ->assertJsonPath('counterpart.phone', '+250 788 000 111')
        ->assertJsonPath('conversation.product.slug', $product->slug)
        ->assertJsonCount(0, 'messages');

    $conversation = Conversation::sole();
    $this->actingAs($member)->postJson(route('conversations.messages.store', $conversation), ['body' => 'Do you deliver to Huye?'])
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Do you deliver to Huye?')
        ->assertJsonPath('messages.0.mine', true);
    $this->actingAs($company->owner)->getJson(route('conversations.feed', $conversation))
        ->assertOk()
        ->assertJsonPath('counterpart.kind', 'member')
        ->assertJsonPath('messages.0.mine', false);
});

it('keeps the chat feed to participants', function () {
    $conversation = Conversation::factory()->between(userWithRole(Role::BUYER), Company::factory()->create())->create();

    $this->actingAs(userWithRole(Role::BUYER))->getJson(route('conversations.feed', $conversation))->assertForbidden();
});
