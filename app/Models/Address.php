<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $label
 * @property string $lat
 * @property string $lng
 * @property string|null $description
 * @property string|null $landmark
 * @property bool $is_default
 */
#[Fillable(['label', 'lat', 'lng', 'description', 'landmark', 'is_default'])]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
