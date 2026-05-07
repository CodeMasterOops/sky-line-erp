<?php

namespace App\Tenancy;

use Closure;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;

trait TenantDatabaseRule
{
    protected mixed $table;

    protected mixed $column;

    protected array $wheres = [];

    protected array $using = [];

    public function __construct($table, $column = 'NULL')
    {
        $this->column = $column;

        $this->table = $this->resolveTableName($table);
    }

    public function resolveTableName($table)
    {
        if (! str_contains($table, '\\') || ! class_exists($table)) {
            return $table;
        }

        if (is_subclass_of($table, Model::class)) {
            $model = new $table;

            if (str_contains($model->getTable(), '.')) {
                return $table;
            }

            return implode('.', array_map(function (string $part) {
                return trim($part, '.');
            }, array_filter([$model->getConnectionName(), $model->getTable()])));
        }

        return $table;
    }

    public function where($column, $value = null)
    {
        if ($value instanceof Arrayable || is_array($value)) {
            return $this->whereIn($column, $value);
        }

        if ($column instanceof Closure) {
            return $this->using($column);
        }

        if (is_null($value)) {
            return $this->whereNull($column);
        }

        $this->wheres[] = compact('column', 'value');

        return $this;
    }

    public function whereNot($column, $value)
    {
        if ($value instanceof Arrayable || is_array($value)) {
            return $this->whereNotIn($column, $value);
        }

        return $this->where($column, '!'.$value);
    }

    public function whereNull($column)
    {
        return $this->where($column, 'NULL');
    }

    public function whereNotNull($column)
    {
        return $this->where($column, 'NOT_NULL');
    }

    public function whereIn($column, $values)
    {
        return $this->where(function ($query) use ($column, $values) {
            $query->whereIn($column, $values);
        });
    }

    public function whereNotIn($column, $values)
    {
        return $this->where(function ($query) use ($column, $values) {
            $query->whereNotIn($column, $values);
        });
    }

    public function using(Closure $callback): static
    {
        $this->using[] = $callback;

        return $this;
    }

    public function queryCallbacks(): array
    {
        return $this->using;
    }

    protected function formatWheres(): string
    {
        $conditions = collect($this->wheres);

        $column = null;
        $value = null;

        $companyId = TenantService::companyId();

        if ($companyId) {
            $column = 'company_id';
            $value = $companyId;
        }

        if ($column && $value) {
            $tenantCond = collect([
                'column' => $column,
                'value' => $value,
            ]);

            $conditions->push($tenantCond);
        }

        return $conditions->map(function ($where) {
            return $where['column'].','.'"'.str_replace('"', '""', $where['value']).'"';
        })->implode(',');
    }
}
