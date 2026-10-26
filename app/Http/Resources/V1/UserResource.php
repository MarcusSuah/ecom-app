<?php

namespace App\Http\Resources\V1;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Fortify\Features;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The signed-in user as the API shows it (Phase 4). Only what the frontend
 * needs: no password hash, remember token, 2FA secret, IP addresses or
 * review notes. `permissions` is what this request may actually do (for a
 * token, only the token's abilities).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $token = $this->currentAccessToken();
        $isToken = $token instanceof PersonalAccessToken;

        $permissions = $this->isPlatformAdmin()
            ? Permission::where('guard_name', 'web')->orderBy('name')->pluck('name')->all()
            : $this->getAllPermissions()->pluck('name')->sort()->values()->all();
        if ($isToken) {
            $permissions = array_values(array_filter($permissions, fn (string $p) => $token->can($p)));
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'slug' => $this->slug,
            'email_verified' => $this->hasVerifiedEmail(),
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'status' => $this->status?->value,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $permissions,
            'is_platform_admin' => $this->isPlatformAdmin(),
            'two_factor_enabled' => Features::enabled(Features::twoFactorAuthentication()) && filled($this->two_factor_confirmed_at),
            'auth' => $isToken
                ? ['type' => 'token', 'token_id' => $token->getKey(), 'expires_at' => $token->expires_at?->toIso8601String()]
                : ['type' => 'session'],
        ];
    }
}
