<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Products\SearchMarketplace;
use App\Http\Controllers\Controller;
use App\Support\PageMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The storefront search: one box for products, shop items, shops,
 * suppliers and categories.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 24;

    private const SUGGESTIONS_PER_GROUP = 4;

    public function index(Request $request, SearchMarketplace $search): Response
    {
        $query = $request->string('q')->trim()->limit(100, '')->toString();

        return Inertia::render('catalog/search', [
            'meta' => PageMeta::make($query !== '' ? "Search: {$query}" : 'Search', 'Search wholesale products, local shops, suppliers and categories.'),
            'query' => $query,
            'results' => $search->handle($query, self::PER_GROUP),
            'minLength' => SearchMarketplace::MIN_LENGTH,
        ]);
    }

    /**
     * A few matches of each kind while the visitor types (header search).
     */
    public function suggest(Request $request, SearchMarketplace $search): JsonResponse
    {
        $query = $request->string('q')->trim()->limit(100, '')->toString();

        return response()->json(['query' => $query, ...$search->handle($query, self::SUGGESTIONS_PER_GROUP)]);
    }
}
