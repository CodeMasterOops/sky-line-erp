<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Conditionable;

class TenantUnique
{
    use Conditionable;
    use TenantDatabaseRule;

    protected $ignore;

    protected $idColumn = 'id';

    public function ignore($id, $idColumn = null)
    {
        if ($id instanceof Model) {
            return $this->ignoreModel($id, $idColumn);
        }

        $this->ignore = $id;
        $this->idColumn = $idColumn ?? 'id';

        return $this;
    }

    public function ignoreModel($model, $idColumn = null): static
    {
        $this->idColumn = $idColumn ?? $model->getKeyName();
        $this->ignore = $model->{$this->idColumn};

        return $this;
    }

    public function withoutTrashed($deletedAtColumn = 'deleted_at'): static
    {
        $this->whereNull($deletedAtColumn);

        return $this;
    }

    public function __toString()
    {
        return rtrim(sprintf(
            'unique:%s,%s,%s,%s,%s',
            $this->table,
            $this->column,
            $this->ignore ? '"'.addslashes($this->ignore).'"' : 'NULL',
            $this->idColumn,
            $this->formatWheres()
        ), ',');
    }
}
