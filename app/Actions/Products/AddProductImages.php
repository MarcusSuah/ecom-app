<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddProductImages
{
    /**
     * Store resized copies of the uploaded photos (the originals are not
     * kept). A product has at most Product::MAX_IMAGES photos.
     *
     * @param  list<UploadedFile>  $uploads
     */
    public function handle(Product $product, array $uploads): void
    {
        $existing = $product->images()->count();

        if ($existing + count($uploads) > Product::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'A product can have up to '.Product::MAX_IMAGES.' photos. You can add '.max(0, Product::MAX_IMAGES - $existing).' more.',
            ]);
        }

        $disk = Storage::disk('public');
        $position = $existing === 0 ? -1 : (int) $product->images()->max('position');

        foreach ($uploads as $upload) {
            $name = Str::lower(Str::ulid()).'.jpg';
            $path = "products/{$product->id}/{$name}";
            $thumbnailPath = "products/{$product->id}/thumbs/{$name}";

            $disk->put($path, ImageResizer::fit($upload->getRealPath(), 1200));
            $disk->put($thumbnailPath, ImageResizer::square($upload->getRealPath(), 400));

            $product->images()->create([
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'position' => ++$position,
            ]);
        }
    }
}
