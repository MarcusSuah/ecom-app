<?php

namespace App\Enums;

enum ProductUnit: string
{
    case PIECE = 'piece';
    case KG = 'kg';
    case CARTON = 'carton';
    case LITRE = 'litre';
    case METRE = 'metre';
    case SET = 'set';

    public function label(): string
    {
        return match ($this) {
            self::PIECE => 'Piece',
            self::KG => 'Kilogram',
            self::CARTON => 'Carton',
            self::LITRE => 'Litre',
            self::METRE => 'Metre',
            self::SET => 'Set',
        };
    }
}
