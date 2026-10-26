<?php

use App\Actions\Reports\RenderDocument;
use App\Enums\Role;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\User;
use App\Support\MarketplaceSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('gives the buyer and the supplier the quote as PDF, and nobody else', function () {
    $buyer = userWithRole(Role::BUYER);
    $quote = Quote::factory()->for(Rfq::factory()->for($buyer, 'buyer'))->create();

    $this->actingAs($buyer)->get(route('quotes.document', $quote))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->actingAs($quote->company->owner)->get(route('quotes.document', $quote))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('quotes.document', $quote))->assertForbidden();
});

it('puts the marketplace logo on its documents', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('logo.png', 200, 60)->store('marketplaces', 'public');
    app(MarketplaceSettings::class)->save(['logo_path' => $path]);

    expect(RenderDocument::imageData($path))->toStartWith('data:image/png;base64,')
        ->and(RenderDocument::imageData('missing.png'))->toBeNull();
});
