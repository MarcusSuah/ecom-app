<?php

namespace App\Actions\Products;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\ShopProduct;
use App\Notifications\StockRunningLow;
use Illuminate\Support\Facades\DB;

/**
 * Tells the seller when a sale moved a listing into low or out of stock.
 * Only the crossing alerts: a listing already low that sells again stays
 * quiet until it runs out. Sent after the order's transaction commits.
 */
class AlertLowStock
{
    public function handle(Product|ShopProduct $listing, StockStatus $before): void
    {
        $after = $listing->stockStatus();

        if ($after === $before || ! in_array($after, [StockStatus::LOW_STOCK, StockStatus::OUT_OF_STOCK], true)) {
            return;
        }

        DB::afterCommit(function () use ($listing, $after): void {
            $company = $listing instanceof Product ? $listing->company : $listing->shop->company;

            $company->owner->notify(new StockRunningLow($listing, $after));
        });
    }
}
