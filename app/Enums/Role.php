<?php

namespace App\Enums;

/**
 * Marketplace roles. Roles are global records but are assigned per tenant
 * (Spatie "teams"), so one account can be a buyer on one marketplace and a
 * supplier on another. Platform admins are flagged on the user instead.
 */
enum Role: string
{
    case BUYER = 'buyer';
    case SUPPLIER = 'supplier';
    case SHOP_OWNER = 'shop_owner';
    case VERIFIER = 'verifier';
    case ADMIN = 'admin';
    /** Holds every permission. Given to the default superadmin account. */
    case SUPER_ADMIN = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::BUYER => 'Buyer',
            self::SUPPLIER => 'Supplier',
            self::SHOP_OWNER => 'Shop owner',
            self::VERIFIER => 'Verifier',
            self::ADMIN => 'Admin',
            self::SUPER_ADMIN => 'Super admin',
        };
    }

    /**
     * Roles that cannot be renamed or deleted from the admin screens.
     *
     * @return list<string>
     */
    public static function protected(): array
    {
        return self::values();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
