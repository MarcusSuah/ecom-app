<?php

namespace App\Enums;

/**
 * Who an admin announcement goes to on the marketplace.
 */
enum AnnouncementAudience: string
{
    case EVERYONE = 'everyone';
    case BUYERS = 'buyers';
    case SUPPLIERS = 'suppliers';
    case SHOP_OWNERS = 'shop_owners';
    case STAFF = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::EVERYONE => 'Every member',
            self::BUYERS => 'Buyers',
            self::SUPPLIERS => 'Suppliers',
            self::SHOP_OWNERS => 'Shop owners',
            self::STAFF => 'Staff (admins and verifiers)',
        };
    }

    /**
     * Roles that make up the audience; null means anyone with a role here.
     *
     * @return list<string>|null
     */
    public function roles(): ?array
    {
        return match ($this) {
            self::EVERYONE => null,
            self::BUYERS => [Role::BUYER->value],
            self::SUPPLIERS => [Role::SUPPLIER->value],
            self::SHOP_OWNERS => [Role::SHOP_OWNER->value],
            self::STAFF => [Role::ADMIN->value, Role::VERIFIER->value, Role::SUPER_ADMIN->value],
        };
    }
}
