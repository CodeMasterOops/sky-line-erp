<?php

namespace App\Services;

class TenantService
{
    protected static ?int $companyId = null;

    protected static ?int $branchId = null;

    protected static ?int $branchRoleId = null;

    protected static ?array $branchPermissions = null;

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

    public static function setBranchRole(?int $roleId): void
    {
        static::$branchRoleId = $roleId;
    }

    public static function branchRoleId(): ?int
    {
        return static::$branchRoleId;
    }

    public static function setBranchPermissions(?array $permissions): void
    {
        static::$branchPermissions = $permissions;
    }

    public static function branchPermissions(): ?array
    {
        return static::$branchPermissions;
    }

    public static function reset(): void
    {
        static::$companyId = null;
        static::$branchId = null;
        static::$branchRoleId = null;
        static::$branchPermissions = null;
    }
}
