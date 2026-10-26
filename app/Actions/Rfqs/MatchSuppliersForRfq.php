<?php

namespace App\Actions\Rfqs;

use App\Enums\CompanyType;
use App\Enums\ProductStatus;
use App\Models\Company;
use App\Models\Rfq;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class MatchSuppliersForRfq
{
    public const MAX_RECIPIENTS = 20;

    /**
     * Verified suppliers with at least one active product in the RFQ's
     * category (or its sub-categories) whose MOQ fits the requested
     * quantity, at most 20. When the RFQ started from a product, that
     * product's supplier is always included if verified. The buyer's own
     * company is never included.
     *
     * @return Collection<int, Company>
     */
    public function handle(Rfq $rfq, bool $onlyProductSupplier = false): Collection
    {
        $categoryIds = [$rfq->category_id, ...$rfq->category->children()->pluck('id')->all()];
        $ownCompanyId = Company::query()->where('owner_id', $rfq->buyer_id)->value('id');

        $verifiedSuppliers = fn (): Builder => Company::query()
            ->ofType(CompanyType::SUPPLIER)
            ->verified()
            ->listable()
            ->when($ownCompanyId, fn (Builder $query) => $query->whereKeyNot($ownCompanyId));

        $pinned = new EloquentCollection;
        if ($rfq->product_id !== null) {
            $pinned = $verifiedSuppliers()
                ->whereHas('products', fn (Builder $query) => $query->whereKey($rfq->product_id))
                ->get();
        }

        // An inquiry about one listing (the storefront's inquiry list) goes to its supplier alone.
        if ($onlyProductSupplier) {
            return $pinned->values();
        }

        $matches = $verifiedSuppliers()
            ->whereHas('products', fn (Builder $query) => $query
                ->where('status', ProductStatus::ACTIVE)
                ->whereIn('category_id', $categoryIds)
                ->where('moq', '<=', $rfq->quantity))
            ->whereKeyNot($pinned->modelKeys())
            // Featured suppliers first, then the longest-standing.
            ->orderByDesc('is_featured')
            ->orderBy('id')
            ->limit(self::MAX_RECIPIENTS - $pinned->count())
            ->get();

        return $pinned->concat($matches)->values();
    }
}
