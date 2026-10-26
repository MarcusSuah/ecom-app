<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Disputes\ResolveDispute;
use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\OrderController;
use App\Models\Dispute;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guarded by the disputes.resolve permission on the routes.
 */
class DisputeController extends Controller
{
    public function index(Request $request): Response
    {
        $status = DisputeStatus::tryFrom($request->string('status')->toString()) ?? DisputeStatus::OPEN;

        return Inertia::render('Admin/Disputes/Index', [
            'disputes' => Dispute::query()
                ->where('status', $status)
                ->with(['order:id,number,total,currency,status,seller_company_id,buyer_id', 'order.seller:id,name', 'order.buyer:id,name,email', 'resolver:id,name'])
                ->when($status === DisputeStatus::OPEN, fn ($query) => $query->oldest('id'), fn ($query) => $query->latest('id'))
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Dispute $dispute) => [
                    'id' => $dispute->id,
                    'order' => $dispute->order->number,
                    'seller' => $dispute->order->seller->name,
                    'buyer' => $dispute->order->buyer->name,
                    'reason' => $dispute->reason->label(),
                    'status' => $dispute->status->value,
                    'created_at' => $dispute->created_at?->toISOString(),
                    // Shown in the row's quick-view modal.
                    'details' => $dispute->details,
                    'buyer_email' => $dispute->order->buyer->email,
                    'order_total' => Money::format($dispute->order->total, $dispute->order->currency),
                    'order_status' => $dispute->order->status->label(),
                    'resolution_note' => $dispute->resolution_note,
                    'resolved_at' => $dispute->resolved_at?->toISOString(),
                    'resolved_by' => $dispute->resolver?->name,
                ]),
            'filters' => ['status' => $status->value],
        ]);
    }

    public function show(Dispute $dispute): Response
    {
        return Inertia::render('Admin/Disputes/Show', [
            'dispute' => [
                'id' => $dispute->id,
                'status' => $dispute->status->value,
                'reason' => $dispute->reason->label(),
                'details' => $dispute->details,
                'resolution_note' => $dispute->resolution_note,
                'resolved_at' => $dispute->resolved_at?->toISOString(),
                'created_at' => $dispute->created_at?->toISOString(),
            ],
            ...OrderController::detail($dispute->order),
        ]);
    }

    public function resolve(Request $request, Dispute $dispute, ResolveDispute $resolveDispute): RedirectResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', Rule::in([DisputeStatus::RESOLVED_RELEASE->value, DisputeStatus::RESOLVED_REFUND->value])],
            'note' => ['required', 'string', 'max:2000'],
        ], [
            'note.required' => 'Explain the decision; both sides will see it.',
        ]);

        $resolveDispute->handle($dispute, $request->user(), DisputeStatus::from($validated['outcome']), $validated['note']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dispute resolved.']);

        return to_route('admin.disputes.index');
    }
}
