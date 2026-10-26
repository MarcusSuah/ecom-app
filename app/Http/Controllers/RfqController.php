<?php

namespace App\Http\Controllers;

use App\Actions\Rfqs\AcceptQuote;
use App\Actions\Rfqs\CloseRfq;
use App\Actions\Rfqs\CreateRfq;
use App\Actions\Suppliers\SupplierResponseTime;
use App\Enums\ProductUnit;
use App\Enums\QuoteStatus;
use App\Http\Requests\Rfqs\StoreRfqRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteTier;
use App\Models\Rfq;
use App\Support\Money;
use App\Support\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A buyer's requests for quotation.
 */
class RfqController extends Controller
{
    /**
     * Session key for a guest's request, kept while they sign in.
     */
    public function index(Request $request): Response
    {
        $rfqs = Rfq::query()
            ->where('buyer_id', $request->user()->id)
            ->with('category:id,name')
            ->withCount(['quotes' => fn ($query) => $query->where('status', '!=', QuoteStatus::WITHDRAWN), 'recipients'])
            ->latest('id')
            ->paginate(20)
            ->through(fn (Rfq $rfq) => [
                'id' => $rfq->id,
                'title' => $rfq->title,
                'quantity' => $rfq->quantity,
                'unit' => $rfq->unit->value,
                'category' => $rfq->category->name,
                'status' => $rfq->status->value,
                'quotes_count' => $rfq->quotes_count,
                'recipients_count' => $rfq->recipients_count,
                'deliver_city' => $rfq->deliver_city,
                'needed_by' => $rfq->needed_by?->toDateString(),
                'notes' => $rfq->notes,
                'expires_at' => $rfq->expires_at->toISOString(),
                'created_at' => $rfq->created_at?->toISOString(),
            ]);

        return Inertia::render('rfqs/index', ['rfqs' => $rfqs]);
    }

    /**
     * The full request form. It is pre-filled from a product page (quick
     * form) or from a draft a guest saved before signing in.
     */
    public function create(Request $request): Response
    {
        $product = $request->filled('product')
            ? Product::query()->active()->whereKey($request->integer('product'))->first()
            : null;

        return Inertia::render('catalog/request-quote', [
            'defaults' => [
                // The landing page's quick quote card sends ?title=&quantity=.
                'title' => $product->name ?? mb_substr($request->string('title')->trim()->toString(), 0, 200),
                'category_id' => (string) ($product->category_id ?? ''),
                'product_id' => $product?->id,
                'quantity' => (string) ($request->integer('quantity') ?: ($product->moq ?? '')),
                'unit' => $product?->unit->value ?? ProductUnit::PIECE->value,
                'deliver_city' => '',
                'needed_by' => '',
                'notes' => '',
                ...self::emptyGuestFields(),
            ],
            'product' => $product ? ['name' => $product->name, 'slug' => $product->slug, 'moq' => $product->moq] : null,
            ...self::formOptions(),
        ]);
    }

    /**
     * Contact fields a guest fills in instead of signing in.
     *
     * @return array{guest_name: string, guest_email: string, guest_phone: string}
     */
    public static function emptyGuestFields(): array
    {
        return ['guest_name' => '', 'guest_email' => '', 'guest_phone' => ''];
    }

    /**
     * Choices for the request form, also used by the landing page.
     *
     * @return array{categories: list<array{id: int, name: string}>, units: list<array{value: string, label: string}>}
     */
    public static function formOptions(): array
    {
        return [
            'categories' => array_values(Category::query()->with('parent:id,name')->ordered()->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->parent ? "{$category->parent->name} › {$category->name}" : $category->name,
                ])->sortBy('name')->all()),
            'units' => array_map(fn (ProductUnit $unit) => ['value' => $unit->value, 'label' => $unit->label()], ProductUnit::cases()),
        ];
    }

    public function store(StoreRfqRequest $request, CreateRfq $createRfq): RedirectResponse
    {
        $fields = Arr::except($request->validated(), ['guest_name', 'guest_email', 'guest_phone']);
        $guest = $request->user() ? null : [
            'name' => (string) $request->validated('guest_name'),
            'email' => (string) $request->validated('guest_email'),
            'phone' => $request->validated('guest_phone'),
        ];

        // Signed-in buyers give a phone number once; it is kept on their account.
        if ($request->user() && blank($request->user()->phone)) {
            $request->user()->forceFill(['phone' => $request->validated('guest_phone')])->save();
        }

        $rfq = $createRfq->handle($request->user(), $fields, $guest);

        $count = $rfq->recipients()->count();
        Inertia::flash('toast', [
            'type' => $count > 0 ? 'success' : 'info',
            'message' => $count > 0
                ? "Your request was sent to {$count} verified ".($count === 1 ? 'supplier' : 'suppliers').'.'
                : 'Your request is posted. No verified supplier matches it yet; we will keep it open for 14 days.',
        ]);

        // Guests follow their request from a private link, also sent by email.
        return $guest
            ? to_route('rfqs.track', $rfq->access_token)
            : to_route('rfqs.show', $rfq);
    }

    /**
     * The quote comparison.
     */
    public function show(Rfq $rfq, SupplierResponseTime $responseTime): Response
    {
        Gate::authorize('view', $rfq);

        return Inertia::render('rfqs/show', $this->details($rfq, $responseTime));
    }

    /**
     * A guest's view of their request and its quotes, from the link we emailed.
     * Accepting a quote needs an account with the same email.
     */
    public function track(string $token, SupplierResponseTime $responseTime): Response
    {
        $rfq = Rfq::query()->where('access_token', $token)->whereNotNull('access_token')->firstOrFail();

        return Inertia::render('catalog/rfq-track', [
            ...$this->details($rfq, $responseTime),
            'guest' => ['name' => $rfq->guest_name, 'email' => $rfq->guest_email, 'claimed' => $rfq->buyer_id !== null],
            'meta' => PageMeta::make('Your request for quotes', "Quotes for {$rfq->title}."),
        ]);
    }

    /**
     * The request and its live quotes, cheapest total first per currency.
     *
     * @return array{rfq: array<string, mixed>, quotes: list<array<string, mixed>>}
     */
    private function details(Rfq $rfq, SupplierResponseTime $responseTime): array
    {
        $rfq->load('category:id,name', 'product:id,name,slug');
        $quotes = $rfq->quotes()
            ->where('status', '!=', QuoteStatus::WITHDRAWN)
            ->with(['tiers', 'company.latestVerification'])
            ->get()
            ->map(function (Quote $quote) use ($rfq, $responseTime) {
                $tier = $quote->tierFor($rfq->quantity);

                return [
                    'id' => $quote->id,
                    'status' => $quote->status->value,
                    'currency' => $quote->currency,
                    'unit_price' => $tier?->unit_price,
                    'formatted_unit_price' => $tier ? Money::format($tier->unit_price, $quote->currency) : null,
                    'total' => $tier ? $tier->unit_price * $rfq->quantity : null,
                    'formatted_total' => $tier ? Money::format($tier->unit_price * $rfq->quantity, $quote->currency) : null,
                    'lead_time' => $quote->leadTimeLabel(),
                    'sample_terms' => $quote->sample_terms,
                    'valid_until' => $quote->valid_until->toDateString(),
                    'message' => $quote->message,
                    'order_id' => $quote->status === QuoteStatus::ACCEPTED ? Order::query()->where('quote_id', $quote->id)->value('id') : null,
                    'tiers' => $quote->tiers->map(fn (QuoteTier $quoteTier) => [
                        'min_qty' => $quoteTier->min_qty,
                        'formatted_unit_price' => Money::format($quoteTier->unit_price, $quote->currency),
                    ])->values(),
                    'supplier' => [
                        'name' => $quote->company->name,
                        'slug' => $quote->company->slug,
                        'is_verified' => $quote->company->isVerified(),
                        'response_time' => $responseTime->label($quote->company),
                    ],
                ];
            })
            // Cheapest total first, per currency.
            ->sortBy([['currency', 'asc'], ['total', 'asc']])
            ->values();

        return [
            'rfq' => [
                'id' => $rfq->id,
                'title' => $rfq->title,
                'quantity' => $rfq->quantity,
                'unit' => $rfq->unit->value,
                'category' => $rfq->category->name,
                'product' => $rfq->product ? ['name' => $rfq->product->name, 'slug' => $rfq->product->slug] : null,
                'deliver_city' => $rfq->deliver_city,
                'needed_by' => $rfq->needed_by?->toDateString(),
                'notes' => $rfq->notes,
                'status' => $rfq->status->value,
                'expires_at' => $rfq->expires_at->toISOString(),
                'recipients_count' => $rfq->recipients()->count(),
            ],
            'quotes' => array_values($quotes->all()),
        ];
    }

    public function close(Rfq $rfq, CloseRfq $closeRfq): RedirectResponse
    {
        Gate::authorize('manage', $rfq);

        $closeRfq->handle($rfq);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request closed.']);

        return back();
    }

    public function accept(Rfq $rfq, Quote $quote, AcceptQuote $acceptQuote): RedirectResponse
    {
        Gate::authorize('manage', $rfq);

        $acceptQuote->handle($rfq, $quote);

        Inertia::flash('toast', ['type' => 'success', 'message' => "You accepted {$quote->company->name}'s quote. The other suppliers have been told."]);

        return back();
    }
}
