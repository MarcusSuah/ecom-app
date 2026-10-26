<?php

namespace Database\Seeders;

use App\Actions\Chat\SendMessage;
use App\Actions\Chat\StartConversation;
use App\Actions\Quotes\SubmitQuote;
use App\Actions\Rfqs\CreateRfq;
use App\Models\Category;
use App\Models\Company;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Requests, quotes and a chat for the demo buyer on the current
 * marketplace. Run by DemoSeeder after the catalogue.
 */
class DemoRfqSeeder extends Seeder
{
    public function __construct(
        private CreateRfq $createRfq,
        private SubmitQuote $submitQuote,
        private StartConversation $startConversation,
        private SendMessage $sendMessage,
    ) {}

    public function run(): void
    {
        $buyer = User::query()->where('email', 'buyer@example.com')->firstOrFail();

        if (Rfq::query()->where('buyer_id', $buyer->id)->exists()) {
            return;
        }

        // Notify suppliers right away instead of leaving jobs for a queue worker.
        config(['queue.default' => 'sync']);

        $requests = [
            ['Maize flour, 25 kg bags', 'Food & Beverages', 2000, 'kg', 'Kigali', 'Food-grade bags, delivered to our warehouse in Kicukiro.'],
            ['Portland cement for a school build', 'Construction Materials', 800, 'piece', 'Huye', 'Grade 42.5, deliveries in two batches.'],
            ['School uniform shirts', 'Apparel & Textiles', 600, 'piece', 'Musanze', 'Light blue, sizes 8–16.'],
        ];

        foreach ($requests as $index => [$title, $categoryName, $quantity, $unit, $city, $notes]) {
            $rfq = $this->createRfq->handle($buyer, [
                'title' => $title,
                'category_id' => Category::query()->where('name', $categoryName)->value('id'),
                'quantity' => $quantity,
                'unit' => $unit,
                'deliver_city' => $city,
                'needed_by' => now()->addWeeks(4 + $index)->toDateString(),
                'notes' => $notes,
            ]);

            foreach ($rfq->recipients()->with('company')->get() as $position => $recipient) {
                // Back-date notifications so suppliers show realistic response times.
                $recipient->update(['notified_at' => now()->subHours(6 + $position * 5)]);

                if ($position > 1) {
                    continue;
                }

                $basePrice = [900, 12000, 4300][$index] - $position * ([900, 12000, 4300][$index] / 20);
                $this->submitQuote->handle($rfq, $recipient->company, [
                    'currency' => 'RWF',
                    'lead_time_min_days' => 5 + $position * 2,
                    'lead_time_max_days' => 12 + $position * 3,
                    'sample_terms' => $position === 0 ? 'Two free samples, collected from our depot.' : 'Samples at cost, refunded on order.',
                    'valid_until' => now()->addDays(30)->toDateString(),
                    'message' => "Thank you for the request. We can deliver to {$city}.",
                ], [
                    ['min_qty' => $quantity, 'unit_price' => (int) round($basePrice)],
                    ['min_qty' => $quantity * 3, 'unit_price' => (int) round($basePrice * 0.92)],
                ]);
            }
        }

        $supplier = Company::query()->where('name', 'Rwanda Agro Supplies')->firstOrFail();
        $conversation = $this->startConversation->handle($buyer, $supplier);
        $this->sendMessage->handle($conversation, $buyer, 'Hello, do you deliver to Rubavu as well?');
        $this->sendMessage->handle($conversation, $supplier->owner, 'Yes, we deliver across Rwanda. Delivery to Rubavu takes 2–3 days.');
    }
}
