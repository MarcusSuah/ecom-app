<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Support\ActingCompany;
use App\Support\CurrentTenant;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $is_platform_admin
 * @property UserStatus $status
 * @property bool $is_active
 * @property CarbonImmutable|Carbon|null $blocked_at
 * @property string|null $phone
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property-read string|null $avatar_url
 * @property-read string $display_status
 */
#[Fillable(['name', 'slug', 'status', 'email', 'password', 'phone', 'address', 'avatar', 'is_active', 'is_verified', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'approved_by', 'approved_at', 'blocked_at', 'blocked_reason', 'last_login_at', 'last_login_ip'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $guard_name = 'web';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'status' => UserStatus::class,
            'is_active' => 'boolean',
            'blocked_at' => 'datetime',
            'last_login_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Companies this user owns. Tenant-scoped, so on a marketplace this is
     * at most the one company they own there.
     *
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    /**
     * The company this user owns on the current marketplace, if any, or the
     * one a super admin is managing on its behalf (ActingCompany).
     */
    public function currentCompany(): ?Company
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return null;
        }

        return app(ActingCompany::class)->for($this, $tenantId) ?? $this->companies()->first();
    }

    /**
     * Owns the company, or is managing it as a super admin.
     */
    public function managesCompany(Company $company): bool
    {
        return $company->owner_id === $this->id || $this->currentCompany()?->is($company) === true;
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Users whose accounts were reviewed by this user.
     */
    public function reviewedUsers()
    {
        return $this->hasMany(User::class, 'reviewed_by');
    }

    /**
     * Determine whether the user is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === UserStatus::APPROVED;
    }

    /**
     * Determine whether the user is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === UserStatus::PENDING;
    }

    /**
     * Determine whether the user is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === UserStatus::REJECTED;
    }

    /**
     * Determine whether the user can access the application.
     */
    public function canAccessApplication(): bool
    {
        return $this->status === UserStatus::APPROVED
            && $this->is_active;
    }

    protected function displayStatus(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->status === UserStatus::PENDING => 'pending',
            $this->status === UserStatus::REJECTED => 'rejected',
            $this->status === UserStatus::APPROVED && ! $this->is_active => 'inactive',
            default => 'active',
        });
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn () => $this->avatar ? Storage::url($this->avatar) : null);
    }
}
