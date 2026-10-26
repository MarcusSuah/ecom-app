<?php

namespace App\Actions\Chat;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartConversation
{
    /**
     * Open (or reuse) the chat between a member and a company's owner,
     * optionally about an RFQ or a product. The same pair and topic always
     * lands in the same conversation.
     */
    public function handle(User $user, Company $company, ?Rfq $rfq = null, ?Product $product = null): Conversation
    {
        if ($company->owner_id === $user->id) {
            throw ValidationException::withMessages(['company' => 'You cannot start a chat with your own company.']);
        }

        $existing = Conversation::query()
            ->where('company_id', $company->id)
            ->where('rfq_id', $rfq?->id)
            ->where('product_id', $product?->id)
            ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $company, $rfq, $product): Conversation {
            $conversation = Conversation::create([
                'company_id' => $company->id,
                'rfq_id' => $rfq?->id,
                'product_id' => $product?->id,
                'subject' => match (true) {
                    $rfq !== null => "Request: {$rfq->title}",
                    $product !== null => $product->name,
                    default => $company->name,
                },
            ]);

            $conversation->participants()->attach([$user->id, $company->owner_id]);

            return $conversation;
        });
    }
}
