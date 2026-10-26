<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateWholesaleOrder;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Rfq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Turning an accepted quote, or a product page, into a wholesale order.
 */
class WholesaleOrderController extends Controller
{
    public function fromQuote(Request $request, Rfq $rfq, Quote $quote, CreateWholesaleOrder $createWholesaleOrder): RedirectResponse
    {
        Gate::authorize('manage', $rfq);
        abort_unless($quote->rfq_id === $rfq->id, 404);

        $order = $createWholesaleOrder->fromQuote($quote, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order created. Pay to send it to the supplier.']);

        return to_route('orders.show', $order);
    }

    public function fromProduct(Request $request, Product $product, CreateWholesaleOrder $createWholesaleOrder): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'deliver_to' => ['required', 'string', 'max:100'],
        ], [
            'deliver_to.required' => 'Say where to deliver.',
        ]);

        $order = $createWholesaleOrder->fromProduct($product, $request->user(), $validated['quantity'], $validated['deliver_to']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order created. Pay to send it to the supplier.']);

        return to_route('orders.show', $order);
    }
}
