<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\DisputeReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Verifications\SubmitVerificationRequest;
use App\Models\Plan;
use App\Models\Rfq;
use App\Support\CurrentTenant;
use App\Support\DisplayCurrency;
use App\Support\PageMeta;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The footer's information pages. Copy lives in the page components; the
 * numbers they quote (plans, commission, release days) come from here so
 * they never drift from what the marketplace actually does.
 */
class ContentPageController extends Controller
{
    /**
     * @var array<string, array{title: string, description: string}>
     */
    public const PAGES = [
        'sell' => [
            'title' => 'List your products',
            'description' => 'Sell wholesale or run a local shop: plans, commission and how to start.',
        ],
        'get-verified' => [
            'title' => 'Get verified',
            'description' => 'What verification checks, the documents to upload and how long the review takes.',
        ],
        'seller-guide' => [
            'title' => 'Seller guide',
            'description' => 'Listing products, answering requests for quotes, fulfilling orders and getting paid.',
        ],
        'payment-protection' => [
            'title' => 'Payment protection',
            'description' => 'How payments are held until you confirm delivery, and what happens if something goes wrong.',
        ],
        'shipping-and-customs' => [
            'title' => 'Shipping and customs',
            'description' => 'Local delivery fees, wholesale shipping, lead times and importing goods.',
        ],
    ];

    public function __invoke(string $page, CurrentTenant $currentTenant, DisplayCurrency $displayCurrency): Response
    {
        $tenant = $currentTenant->require();
        $protectionLabel = (string) config('payments.protection_label');
        $info = self::PAGES[$page];
        $title = $page === 'payment-protection' ? $protectionLabel : $info['title'];

        return Inertia::render('content/'.$page, [
            'meta' => PageMeta::make($title, $info['description']),
            'title' => $title,
            'facts' => [
                'autoReleaseDays' => (int) config('payments.auto_release_days'),
                'defaultCommissionPercent' => (float) config('payments.default_commission_percent'),
                'protectionLabel' => $protectionLabel,
                'roadFactor' => (float) config('marketplace.road_factor'),
                'maxDocuments' => SubmitVerificationRequest::MAX_DOCUMENTS,
                'rfqOpenDays' => Rfq::DEFAULT_LIFETIME_DAYS,
            ],
            'plans' => fn (): array => $page === 'sell'
                ? Plan::query()->forSellersOf($tenant)->where('is_active', true)->ordered()->get()
                    ->map(fn (Plan $plan) => [
                        ...$plan->toSummary(),
                        'approx_price' => $plan->isFree() ? null : $displayCurrency->approximate($plan->price, $plan->currency),
                    ])->values()->all()
                : [],
            'disputeReasons' => array_map(fn (DisputeReason $reason) => $reason->label(), DisputeReason::cases()),
        ]);
    }
}
