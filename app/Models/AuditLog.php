<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One change a person made (App\Support\Audit, App\Models\Concerns\Auditable).
 *
 * @property string $id
 * @property int|null $tenant_id
 * @property string $action
 * @property string $table_name
 * @property string|null $record_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string $performed_by
 * @property string|null $performed_by_name
 * @property string $performed_by_role
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $module
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 */
class AuditLog extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'performed_by_name',
        'action', 'table_name', 'record_id', 'old_values', 'new_values', 'performed_by',
        'performed_by_role', 'ip_address', 'user_agent', 'module', 'description',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
