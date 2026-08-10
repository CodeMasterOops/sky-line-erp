<?php

namespace App\Enums;

use App\Models\Unit;
use App\Models\Brand;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Product;
use App\Models\Employee;
use App\Models\Warehouse;
use App\Models\Department;
use App\Models\FixedAsset;

enum EntityCodeType: string
{
    case Product = 'product';
    case Warehouse = 'warehouse';
    case Brand = 'brand';
    case Unit = 'unit';
    case Department = 'department';
    case Employee = 'employee';
    case Branch = 'branch';
    case FixedAsset = 'fixed_asset';
    case Member = 'member';

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Product => Product::class,
            self::Warehouse => Warehouse::class,
            self::Brand => Brand::class,
            self::Unit => Unit::class,
            self::Department => Department::class,
            self::Employee => Employee::class,
            self::Branch => Branch::class,
            self::FixedAsset => FixedAsset::class,
            self::Member => Member::class,
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Product => 'PROD-',
            self::Warehouse => 'WH-',
            self::Brand => 'BR-',
            self::Unit => 'UNIT-',
            self::Department => 'DEPT-',
            self::Employee => 'EMP-',
            self::Branch => 'BRN-',
            self::FixedAsset => 'FA-',
            self::Member => 'MEM-',
        };
    }

    public function column(): string
    {
        return match ($this) {
            self::Employee => 'employee_code',
            self::FixedAsset => 'asset_code',
            self::Member => 'member_code',
            default => 'code',
        };
    }

    public function padding(): int
    {
        // Gyms run to thousands of members; five digits keeps member IDs
        // sortable well past that.
        return $this === self::Member ? 5 : 4;
    }

    /**
     * @return array<string, mixed>
     */
    public function scopes(): array
    {
        return [];
    }
}
