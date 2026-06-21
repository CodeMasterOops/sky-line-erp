<?php

namespace App\Enums;

/**
 * Advisory manufacturing classification for a product. Unlike ProductTypeEnum
 * (which determines whether an item is stockable and posts COGS), ItemRoleEnum
 * is a reporting / smart-default hint only and never gates stock or GL behaviour.
 * A product with no role is simply "unspecified".
 */
enum ItemRoleEnum: string
{
    case RawMaterial = 'raw_material';
    case SemiFinished = 'semi_finished';
    case FinishedGood = 'finished_good';
    case Consumable = 'consumable';
    case TradingGood = 'trading_good';

    public function label(): string
    {
        return match ($this) {
            self::RawMaterial => 'Raw Material',
            self::SemiFinished => 'Semi-Finished (Sub-assembly)',
            self::FinishedGood => 'Finished Good',
            self::Consumable => 'Consumable',
            self::TradingGood => 'Trading Good',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $role): array => ['value' => $role->value, 'label' => $role->label()],
            self::cases(),
        );
    }
}
