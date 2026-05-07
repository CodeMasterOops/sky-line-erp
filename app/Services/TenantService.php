<?php

namespace App\Services;

class TenantService
{
    protected static ?int $companyId = null;

    protected static ?int $branchId = null;

    public static function setCompanyId(?int $companyId): void
    {
        static::$companyId = $companyId;
    }

    public static function companyId(): ?int
    {
        return static::$companyId;
    }

    public static function setBranchId(?int $branchId): void
    {
        static::$branchId = $branchId;
    }

    public static function branchId(): ?int
    {
        return static::$branchId;
    }
}
