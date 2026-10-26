<?php

namespace App\Models;

use App\Enums\Role as RoleEnum;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The five marketplace roles are global (tenant_id null) and can be given
 * on any marketplace. Roles a marketplace admin creates belong to that
 * marketplace only, so other marketplaces never see them.
 */
class Role extends SpatieRole
{
    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $tenantId = app(CurrentTenant::class)->id();

            if ($tenantId !== null) {
                $query->where(fn (Builder $q) => $q
                    ->whereNull($q->qualifyColumn('tenant_id'))
                    ->orWhere($q->qualifyColumn('tenant_id'), $tenantId));
            }
        });
    }

    public function isProtected(): bool
    {
        return in_array($this->name, RoleEnum::protected(), true);
    }
}
