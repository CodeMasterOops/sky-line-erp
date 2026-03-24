<?php

namespace App\Enums;

enum ChangeTypeEnum: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case DAMAGE = 'damage';
    case LOST = 'lost';
    // transfer_in
    // transfer_out
    // adjustment_add
    // adjustment_sub
    // return_in
    // return_out
    // opening_stock

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::PURCHASE => 'Purchase',
            self::SALE => 'Sale',
            self::DAMAGE => 'Damage',
            self::LOST => 'Lost',
        };
    }

    public static function typeList(): array
    {
        $list = [];

        foreach (self::cases() as $status) {
            $list[] = [
                'value' => $status->value,
                'label' => $status->label(),
            ];
        }

        return $list;
    }
}
