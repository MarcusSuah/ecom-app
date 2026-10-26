<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Enums\RfqStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\RfqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * A buyer's request for quotation, sent to matching verified suppliers.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $buyer_id
 * @property string|null $guest_name
 * @property string|null $guest_email
 * @property string|null $guest_phone
 * @property string|null $access_token
 * @property int $category_id
 * @property int|null $product_id
 * @property string $title
 * @property int $quantity
 * @property ProductUnit $unit
 * @property string $deliver_city
 * @property CarbonImmutable|null $needed_by
 * @property string|null $notes
 * @property RfqStatus $status
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $buyer
 * @property-read Category $category
 * @property-read Product|null $product
 */
#[Fillable(['category_id', 'product_id', 'title', 'quantity', 'unit', 'deliver_city', 'needed_by', 'notes'])]
class Rfq extends Model
{
    /** @use HasFactory<RfqFactory> */
    use BelongsToTenant, HasFactory;

    public const DEFAULT_LIFETIME_DAYS = 14;

    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            'status' => RfqStatus::class,
            'quantity' => 'integer',
            'needed_by' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Posted without an account and not yet linked to one.
     */
    public function isGuest(): bool
    {
        return $this->buyer_id === null;
    }

    /**
     * Who suppliers are dealing with: the account, or the guest's details.
     */
    public function contactName(): string
    {
        return $this->buyer->name ?? $this->guest_name ?? 'Buyer';
    }

    /**
     * Where quote notifications go: the buyer account, or the guest's email.
     */
    public function notifyBuyer(Notification $notification): void
    {
        if ($this->buyer) {
            $this->buyer->notify($notification);
        } elseif ($this->guest_email) {
            NotificationFacade::route('mail', [$this->guest_email => $this->guest_name])->notify($notification);
        }
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<RfqRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(RfqRecipient::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function isOpen(): bool
    {
        return $this->status === RfqStatus::OPEN;
    }

    public function recipientFor(Company $company): ?RfqRecipient
    {
        return $this->recipients()->where('company_id', $company->id)->first();
    }
}
