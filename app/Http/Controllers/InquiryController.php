<?php

namespace App\Http\Controllers;

use App\Actions\Rfqs\CreateRfq;
use App\Http\Requests\Rfqs\StoreInquiryRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Sends the storefront's inquiry list: one request for quotes per listing,
 * to that listing's supplier only, at no less than its minimum order.
 */
class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request, CreateRfq $createRfq): RedirectResponse
    {
        $user = $request->user();
        $guest = $user ? null : [
            'name' => (string) $request->validated('guest_name'),
            'email' => (string) $request->validated('guest_email'),
            'phone' => $request->validated('guest_phone'),
        ];

        if ($user && blank($user->phone)) {
            $user->forceFill(['phone' => $request->validated('guest_phone')])->save();
        }

        $quantities = collect($request->items())->pluck('quantity', 'product_id');
        $products = Product::query()->active()->with('company:id,owner_id')->whereKey($quantities->keys())->get();
        $own = $products->filter(fn (Product $product) => $user !== null && $product->company->owner_id === $user->id);
        $sendable = $products->diff($own);

        DB::transaction(function () use ($sendable, $quantities, $request, $user, $guest, $createRfq): void {
            foreach ($sendable as $product) {
                $createRfq->handle($user, [
                    'title' => $product->name,
                    'category_id' => $product->category_id,
                    'product_id' => $product->id,
                    'quantity' => max((int) $quantities[$product->id], $product->moq),
                    'unit' => $product->unit,
                    'deliver_city' => $request->validated('deliver_city'),
                    'notes' => $request->validated('notes'),
                ], $guest, onlyProductSupplier: true);
            }
        });

        $suppliers = $sendable->pluck('company_id')->unique()->count();
        Inertia::flash('toast', [
            'type' => $suppliers > 0 ? 'success' : 'info',
            'message' => match (true) {
                $suppliers === 0 => 'Nothing was sent: these are your own products.',
                $guest !== null => "Inquiry sent to {$suppliers} ".str('supplier')->plural($suppliers).'. We emailed you a link to follow each request.',
                default => "Inquiry sent to {$suppliers} ".str('supplier')->plural($suppliers).'. Quotes appear under My requests.',
            },
        ]);
        Inertia::flash('inquirySent', $suppliers > 0);

        return back();
    }
}
