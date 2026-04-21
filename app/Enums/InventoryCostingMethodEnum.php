<?php

namespace App\Enums;

enum InventoryCostingMethodEnum: string
{
    case FIFO = 'fifo';
    case WEIGHTED_AVERAGE = 'weighted_average';

    public function label(): string
    {
        return match ($this) {
            self::FIFO => 'FIFO (first in, first out)',
            self::WEIGHTED_AVERAGE => 'Weighted average',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function optionsForSelect(): array
    {
        $list = [];

        foreach (self::cases() as $case) {
            $list[] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }

        return $list;
    }
}
