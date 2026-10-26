<?php

namespace App\Enums;

enum CompanyType: string
{
    case SUPPLIER = 'supplier';
    case SHOP = 'shop';

    public function label(): string
    {
        return match ($this) {
            self::SUPPLIER => 'Wholesale supplier',
            self::SHOP => 'Local shop',
        };
    }

    /**
     * The role a company owner receives for this type of company.
     */
    public function ownerRole(): Role
    {
        return match ($this) {
            self::SUPPLIER => Role::SUPPLIER,
            self::SHOP => Role::SHOP_OWNER,
        };
    }
}
