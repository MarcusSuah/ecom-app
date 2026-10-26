<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function ownedProduct(): array
{
    $owner = User::factory()->create();
    $product = Product::factory()->for(Company::factory()->for($owner, 'owner')->supplier())->create();

    return [$owner, $product];
}

it('stores a resized photo and a square thumbnail', function () {
    Storage::fake('public');
    [$owner, $product] = ownedProduct();

    $this->actingAs($owner)
        ->post(route('supplier.products.images.store', $product), ['images' => [UploadedFile::fake()->image('flour.png', 2400, 1600)]])
        ->assertSessionHasNoErrors();

    $image = ProductImage::sole();
    Storage::disk('public')->assertExists([$image->path, $image->thumbnail_path]);

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($image->path));
    [$thumbWidth, $thumbHeight] = getimagesizefromstring(Storage::disk('public')->get($image->thumbnail_path));
    expect([$width, $height])->toBe([1200, 800])
        ->and([$thumbWidth, $thumbHeight])->toBe([400, 400]);
});

it('allows at most ten photos per product', function () {
    Storage::fake('public');
    [$owner, $product] = ownedProduct();
    $product->images()->createMany(array_fill(0, 9, ['path' => 'p.jpg', 'thumbnail_path' => 't.jpg']));

    $this->actingAs($owner)
        ->post(route('supplier.products.images.store', $product), ['images' => [
            UploadedFile::fake()->image('a.jpg', 400, 400),
            UploadedFile::fake()->image('b.jpg', 400, 400),
        ]])
        ->assertSessionHasErrors(['images' => 'A product can have up to 10 photos. You can add 1 more.']);

    expect($product->images()->count())->toBe(9);
});

it('rejects files that are not photos', function () {
    Storage::fake('public');
    [$owner, $product] = ownedProduct();

    $this->actingAs($owner)
        ->post(route('supplier.products.images.store', $product), ['images' => [UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf')]])
        ->assertSessionHasErrors('images.0');
});

it('only lets the owner remove a photo', function () {
    Storage::fake('public');
    [, $product] = ownedProduct();
    $image = $product->images()->create(['path' => 'p.jpg', 'thumbnail_path' => 't.jpg']);
    [$otherOwner] = ownedProduct();

    $this->actingAs($otherOwner)
        ->delete(route('supplier.products.images.destroy', ['product' => $product, 'image' => $image]))
        ->assertForbidden();

    expect($image->fresh())->not->toBeNull();
});
