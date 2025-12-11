<?php

namespace App\Tenancy;

use Illuminate\Support\Traits\Conditionable;

class TenantExists
{
    use Conditionable;
    use TenantDatabaseRule;

    public function withoutTrashed($deletedAtColumn = 'deleted_at'): static
    {
        $this->whereNull($deletedAtColumn);

        return $this;
    }

    public function __toString()
    {
        return rtrim(sprintf(
            'exists:%s,%s,%s',
            $this->table,
            $this->column,
            $this->formatWheres()
        ), ',');
    }
}
