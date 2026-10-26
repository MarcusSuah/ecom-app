<?php

use App\Actions\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('saves photos with a new product and puts the chosen one first', function () {
    Storage::fake('public');
    [$owner] = supplierOwner();

    $this->actingAs($owner)->post(route('supplier.products.store'), productPayload([
        'images' => [UploadedFile::fake()->image('front.jpg', 800, 600), UploadedFile::fake()->image('label.jpg', 800, 600)],
        'cover' => 'new:1',
        'stock' => '2000',
    ]))->assertSessionHasNoErrors();

    $product = Product::sole();
    [$featured, $other] = $product->images()->get()->all();
    expect($product->stock)->toBe(2000)
        ->and($featured->position)->toBe(0)
        ->and($other->position)->toBe(1)
        ->and($product->coverImage->is($featured))->toBeTrue();
    Storage::disk('public')->assertExists([$featured->path, $featured->thumbnail_path, $other->path]);
});

it('removes photos and changes the featured one when the form is saved', function () {
    Storage::fake('public');
    [$owner] = supplierOwner();
    $this->actingAs($owner)->post(route('supplier.products.store'), productPayload([
        'images' => [UploadedFile::fake()->image('a.jpg', 400, 400), UploadedFile::fake()->image('b.jpg', 400, 400), UploadedFile::fake()->image('c.jpg', 400, 400)],
    ]));
    $product = Product::sole();
    [$a, $b, $c] = $product->images()->get()->all();

    $this->actingAs($owner)->put(route('supplier.products.update', $product), productPayload([
        'category_id' => $product->category_id,
        'remove_images' => [$a->id],
        'cover' => "existing:{$c->id}",
    ]))->assertSessionHasNoErrors();

    expect($product->images()->pluck('id')->all())->toBe([$c->id, $b->id]);
    Storage::disk('public')->assertMissing($a->path);
});

it('refuses more photos than a product can have', function () {
    Storage::fake('public');
    [$owner] = supplierOwner();

    $this->actingAs($owner)->post(route('supplier.products.store'), productPayload([
        'images' => array_map(fn (int $i) => UploadedFile::fake()->image("{$i}.jpg", 300, 300), range(1, Product::MAX_IMAGES + 1)),
    ]))->assertSessionHasErrors('images');
});

it('shows stock and sales badges, and stops orders once stock cannot fill the MOQ', function () {
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['moq' => 100, 'stock' => 99]);
    $buyer = User::factory()->create();

    $this->get(route('products.show', $product))->assertInertia(fn (Assert $page) => $page
        ->where('product.stock_status', 'out_of_stock')
        ->where('product.stock_label', 'Out of stock'));

    $this->actingAs($buyer)->post(route('products.order', $product), ['quantity' => 100, 'deliver_to' => 'Kigali'])
        ->assertSessionHasErrors(['quantity' => 'This product is out of stock.']);
    expect(Order::count())->toBe(0);
});

it('takes ordered units out of stock, counts them as sold when paid and puts them back on refund', function () {
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['moq' => 100, 'stock' => 500]);
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->post(route('products.order', $product), ['quantity' => 600, 'deliver_to' => 'Kigali'])
        ->assertSessionHasErrors(['quantity' => 'Only 500 piece are in stock.']);

    $this->actingAs($buyer)->post(route('products.order', $product), ['quantity' => 200, 'deliver_to' => 'Kigali'])->assertSessionHasNoErrors();
    $order = Order::sole();
    expect($product->fresh())->stock->toBe(300)->units_sold->toBe(0);

    $machine = app(OrderStateMachine::class);
    $machine->transition($order, OrderStatus::PAID);
    expect($product->fresh()->units_sold)->toBe(200);

    $machine->transition($order->fresh(), OrderStatus::REFUNDED);
    expect($product->fresh())->stock->toBe(500)->units_sold->toBe(0);
});

it('leaves made-to-order products without a stock limit', function () {
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['moq' => 100, 'stock' => null]);

    $this->actingAs(User::factory()->create())->post(route('products.order', $product), ['quantity' => 50000, 'deliver_to' => 'Kigali'])->assertSessionHasNoErrors();

    expect($product->fresh()->stock)->toBeNull()
        ->and($product->stockBadge()['stock_status'])->toBe('made_to_order');
});

it('accepts photos of any size and brings them to the gallery format', function () {
    Storage::fake('public');
    [$owner] = supplierOwner();

    $this->actingAs($owner)->post(route('supplier.products.store'), productPayload([
        'images' => [UploadedFile::fake()->image('tiny.png', 60, 40), UploadedFile::fake()->image('huge.jpg', 4000, 3000)],
    ]))->assertSessionHasNoErrors();

    [$tiny, $huge] = Product::sole()->images()->get()->all();
    $size = fn (string $path) => getimagesizefromstring(Storage::disk('public')->get($path));

    expect(array_slice($size($tiny->path), 0, 2))->toBe([600, 400])
        ->and(array_slice($size($tiny->thumbnail_path), 0, 2))->toBe([400, 400])
        ->and(array_slice($size($huge->path), 0, 2))->toBe([1200, 900])
        ->and(array_slice($size($huge->thumbnail_path), 0, 2))->toBe([400, 400]);
});
