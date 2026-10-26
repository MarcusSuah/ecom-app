<?php

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteProduct
{
    /**
     * Only drafts are deleted. Products that have been listed are archived
     * instead, so later quotes and orders can still point at them.
     */
    public function handle(Product $product): void
    {
        if ($product->status !== ProductStatus::DRAFT) {
            throw ValidationException::withMessages(['product' => 'Only drafts can be deleted. Archive listed products instead.']);
        }

        $product->delete();
        Storage::disk('public')->deleteDirectory("products/{$product->id}");
    }
}
