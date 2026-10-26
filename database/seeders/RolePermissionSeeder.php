<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * All marketplace permissions, grouped by module.
     *
     * The permission names here MUST match the permission middleware and
     * policies used by the application. Permissions and the five roles are
     * global; which role a user has is decided per marketplace.
     *
     * @var array<string, array<string, string>>
     */
    protected array $permissions = [
        'Dashboard' => [
            'dashboard.view' => 'View your dashboard',
            'admin.dashboard' => 'View the marketplace admin dashboard',
        ],
        'Companies' => [
            'companies.view-any' => 'View every company on the marketplace',
            'companies.feature' => 'Feature or un-feature companies',
            'companies.suspend' => 'Suspend a business (hidden from the storefront) or reinstate it',
            // Super admin only: not given to the admin role.
            'companies.manage' => "Manage any business's products, photos and shop on its behalf",
        ],
        'Verifications' => [
            'verifications.review' => 'Review company verification applications and their documents',
        ],
        'Categories' => [
            'categories.manage' => 'Create, edit and delete categories',
        ],
        'Billing' => [
            'billing.manage' => "Manage the marketplace's own plan with the platform",
            'seller-plans.manage' => 'Create and edit the plans sold to sellers',
            'seller-subscriptions.manage' => "Change a seller's plan and record offline payments",
        ],
        'Users' => [
            'users.view' => 'View marketplace members',
            'users.update' => 'Edit members and their roles',
            'users.delete' => 'Delete accounts',
        ],
        'Roles' => [
            'roles.view' => 'View roles',
            'roles.create' => 'Create roles',
            'roles.update' => 'Edit roles',
            'roles.delete' => 'Delete roles',
        ],
        'Permissions' => [
            'permissions.view' => 'View permissions',
            'permissions.create' => 'Create permissions',
            'permissions.update' => 'Edit permissions',
            'permissions.delete' => 'Delete permissions',
        ],
        'Payments' => [
            'disputes.resolve' => 'Resolve payment disputes by releasing or refunding',
            'payouts.manage' => 'See seller payouts and mark them paid',
        ],
        'Marketplace' => [
            'currency.manage' => 'Override the exchange rate used to show approximate prices',
            'contact.manage' => 'Read and answer contact form messages',
            'settings.manage' => "Edit the marketplace's settings (contact details, tagline, social links)",
            'notifications.send' => 'Send announcements to members of the marketplace',
        ],
        'Reports' => [
            'reports.view' => 'See sales, commission and payout reports and download them as CSV',
        ],
        'Monitoring' => [
            'audit-logs.view' => 'View the audit log of record changes',
            'activity.view' => 'View user activity, devices and locations',
            'activity.manage' => 'Block/unblock users and IP addresses, sign users out',
        ],
    ];

    /**
     * Permissions given to each role. Permissions that change what exists
     * for every marketplace (permissions.*, users.delete) are left to
     * platform admins, who pass every check.
     *
     * @var array<string, list<string>>
     */
    protected array $rolePermissions = [
        'buyer' => ['dashboard.view'],
        'supplier' => ['dashboard.view'],
        'shop_owner' => ['dashboard.view'],
        'verifier' => ['dashboard.view', 'companies.view-any', 'verifications.review'],
        'admin' => [
            'dashboard.view', 'admin.dashboard',
            'companies.view-any', 'companies.feature', 'companies.suspend',
            'verifications.review',
            'categories.manage',
            'billing.manage', 'seller-plans.manage', 'seller-subscriptions.manage',
            'disputes.resolve', 'payouts.manage', 'reports.view',
            'currency.manage', 'contact.manage', 'settings.manage', 'notifications.send',
            'users.view', 'users.update',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'permissions.view',
            'audit-logs.view', 'activity.view', 'activity.manage',
        ],
        // Synced with every permission when seeding (see run()).
        'super_admin' => [],
    ];

    /**
     * Every permission the application itself checks (routes, policies,
     * controllers). These cannot be renamed or deleted from the admin
     * screen, because that would silently switch a feature off.
     *
     * @return list<string>
     */
    public static function systemPermissions(): array
    {
        return array_values(collect((new self)->permissions)->flatMap(fn (array $items) => array_keys($items))->all());
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $module => $items) {
            foreach ($items as $name => $description) {
                Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['module' => $module, 'description' => $description],
                );
            }
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            // Global roles (no tenant), usable on every marketplace.
            $role = Role::query()->withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web', 'tenant_id' => null]);

            // The super admin always holds every permission, including ones added later.
            $role->syncPermissions($roleEnum === RoleEnum::SUPER_ADMIN
                ? Permission::query()->pluck('name')->all()
                : $this->rolePermissions[$roleEnum->value]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
