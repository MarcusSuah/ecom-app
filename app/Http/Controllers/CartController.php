<?php

namespace App\Http\Controllers;

use App\Actions\Cart\ManageCart;
use App\Actions\Cart\SummariseCart;
use App\Actions\Checkout\PlaceOrders;
use App\Enums\Fulfilment;
use App\Models\Address;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Support\CustomerLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private ManageCart $manageCart) {}

    /**
     * Fees are recalculated on every visit, so changing the fulfilment,
     * quantities or location updates them straight away.
     */
    public function show(Request $request, SummariseCart $summariseCart): Response
    {
        return Inertia::render('cart/show', $this->contents($request, $summariseCart));
    }

    /**
     * The same cart as JSON, for the storefront's "Local order" drawer.
     */
    public function summary(Request $request, SummariseCart $summariseCart): JsonResponse
    {
        return response()->json($this->contents($request, $summariseCart));
    }

    /**
     * @return array<string, mixed>
     */
    private function contents(Request $request, SummariseCart $summariseCart): array
    {
        $location = CustomerLocation::get($request);
        $groups = $summariseCart->handle($this->manageCart->cartFor($request->user()), $location);

        return [
            'groups' => SummariseCart::present($groups),
            'location' => $location,
            'savedAddresses' => Address::query()->where('user_id', $request->user()->id)->orderByDesc('is_default')->get(['id', 'label']),
            'neighbourhoods' => config('marketplace.neighbourhoods'),
        ];
    }

    public function add(Request $request, ShopProduct $shopProduct): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.ManageCart::MAX_QUANTITY]]);

        $this->manageCart->add($request->user(), $shopProduct, $validated['quantity']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added {$shopProduct->name} to your cart."]);

        return back();
    }

    public function update(Request $request, ShopProduct $shopProduct): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.ManageCart::MAX_QUANTITY]]);

        $this->manageCart->update($request->user(), $shopProduct, $validated['quantity']);

        return back();
    }

    public function fulfilment(Request $request, Shop $shop): RedirectResponse
    {
        $validated = $request->validate(['fulfilment' => ['required', Rule::enum(Fulfilment::class)]]);

        $this->manageCart->chooseFulfilment($request->user(), $shop, Fulfilment::from($validated['fulfilment']));

        return back();
    }

    public function checkout(Request $request, PlaceOrders $placeOrders): RedirectResponse
    {
        $orders = $placeOrders->handle($request->user(), $this->manageCart->cartFor($request->user()), CustomerLocation::get($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($orders) === 1 ? 'Order placed.' : count($orders).' orders placed, one per shop.',
        ]);

        return to_route('orders.index');
    }
}
