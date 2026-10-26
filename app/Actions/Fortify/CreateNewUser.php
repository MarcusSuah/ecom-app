<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AccountAwaitingApproval;
use App\Support\CurrentTenant;
use App\Support\MarketplaceSettings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $needsApproval = app(MarketplaceSettings::class)->requiresAccountApproval();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'status' => $needsApproval ? UserStatus::PENDING : UserStatus::APPROVED,
        ]);

        // Roles are per marketplace: the sign-up becomes a buyer on the one they signed up on.
        $user->assignRole(Role::BUYER->value);

        if ($needsApproval) {
            $this->notifyUserManagers($user);
        }

        return $user;
    }

    /**
     * The marketplace's admins (anyone who may approve accounts here) hear about the sign-up.
     */
    private function notifyUserManagers(User $user): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            return;
        }

        $managers = User::role([Role::ADMIN->value, Role::SUPER_ADMIN->value])->whereKeyNot($user->id)->get();

        Notification::send($managers, new AccountAwaitingApproval($user, $tenantId));
    }
}
