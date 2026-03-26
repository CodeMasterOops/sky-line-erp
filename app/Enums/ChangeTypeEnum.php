<?php

namespace App\Enums;

enum ChangeTypeEnum: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case DAMAGE = 'damage';
    case LOST = 'lost';
    case TRANSFER_IN = 'transfer-in';
    case TRANSFER_OUT = 'transfer-out';
    case ADJUSTMENT_IN = 'adjustment-in';
    case ADJUSTMENT_OUT = 'adjustment-out';
    case RETURN_IN = 'return-in';
    case RETURN_OUT = 'return-out';
    case OPENING_STOCK = 'opening-stock';

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
            self::TRANSFER_IN => 'Transfer In',
            self::TRANSFER_OUT => 'Transfer Out',
            self::ADJUSTMENT_IN => 'Adjustment In',
            self::ADJUSTMENT_OUT => 'Adjustment Out',
            self::RETURN_IN => 'Return In',
            self::RETURN_OUT => 'Return Out',
            self::OPENING_STOCK => 'Opening Stock',
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
