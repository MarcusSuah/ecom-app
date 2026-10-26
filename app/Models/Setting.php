<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One marketplace setting. Read them through App\Support\MarketplaceSettings,
 * which knows the keys, their defaults and the cache.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use BelongsToTenant;
}
