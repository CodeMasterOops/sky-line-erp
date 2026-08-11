<?php

namespace App\Services\Modules;

use App\Models\Company;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * What switching a module off would actually mean for one company.
 *
 * Informed consent, not a veto: the answer never blocks the toggle and never
 * changes a row. Disabling stays reversible and lossless — this exists so the
 * Super Admin knows, before clicking, that the module holds 4,000 rows, that
 * two other modules will cascade with it, that a till session is open, and that
 * two nightly commands will stop running.
 *
 * Deliberately read-only and best-effort: a model whose table is missing (a
 * half-migrated environment) is reported as unknown rather than throwing, since
 * failing to *describe* an impact must not stop an operator from acting.
 */
class ModuleImpactAnalyzer
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * @return array{
     *     module: string,
     *     name: string,
     *     enabled: bool,
     *     cascade: list<array{key: string, name: string}>,
     *     records: list<array{model: string, label: string, count: ?int}>,
     *     total_records: int,
     *     in_flight: list<array{label: string, count: int, detail: string}>,
     *     scheduled_commands: list<string>,
     *     data_transfer_entities: list<string>,
     *     reversible: bool,
     * }
     */
    public function analyze(Company $company, string $moduleKey): array
    {
        $definition = $this->registry->get($moduleKey);
        $companyId = (int) $company->id;
        $enabledKeys = app(CompanyModuleService::class)->enabledKeys($companyId);

        $cascade = array_values(array_map(
            fn (string $key): array => ['key' => $key, 'name' => $this->registry->get($key)['name']],
            array_intersect($this->registry->dependentsOf($moduleKey), $enabledKeys),
        ));

        $records = $this->recordCounts($definition['models'], $companyId);

        return [
            'module' => $moduleKey,
            'name' => $definition['name'],
            'enabled' => in_array($moduleKey, $enabledKeys, true),
            'cascade' => $cascade,
            'records' => $records,
            'total_records' => array_sum(array_map(fn (array $row): int => $row['count'] ?? 0, $records)),
            'in_flight' => $this->inFlightWork($moduleKey, $companyId),
            'scheduled_commands' => $definition['scheduled_commands'],
            'data_transfer_entities' => $definition['data_transfer_entities'],
            // Stated explicitly because it is the whole point: nothing here is
            // a warning about data loss, because there is none.
            'reversible' => true,
        ];
    }

    /**
     * Row counts per model the module owns, including soft-deleted rows — they
     * are preserved too, and an operator comparing counts afterwards should see
     * the same number.
     *
     * @param  list<class-string>  $models
     * @return list<array{model: string, label: string, count: ?int}>
     */
    private function recordCounts(array $models, int $companyId): array
    {
        $rows = [];

        foreach ($models as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass;

            if (! $model instanceof Model) {
                continue;
            }

            $rows[] = [
                'model' => class_basename($modelClass),
                'label' => $this->humanise(class_basename($modelClass)),
                'count' => $this->countFor($model, $companyId),
            ];
        }

        return $rows;
    }

    private function countFor(Model $model, int $companyId): ?int
    {
        $table = $model->getTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
            return null;
        }

        $query = $model->newQuery()->withoutGlobalScopes()->where('company_id', $companyId);

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withTrashed();
        }

        return (int) $query->count();
    }

    /**
     * Work that is mid-flight right now, and would be left stranded rather than
     * simply hidden. This is the part an operator most needs before clicking.
     *
     * @return list<array{label: string, count: int, detail: string}>
     */
    private function inFlightWork(string $moduleKey, int $companyId): array
    {
        $checks = match ($moduleKey) {
            'pos' => [[
                'label' => 'Open till sessions',
                'table' => 'pos_sessions',
                'where' => ['status' => 'open'],
                'detail' => 'A cashier is mid-shift; the session stays open and resumable.',
            ]],
            'data-transfer' => [
                [
                    'label' => 'Imports awaiting commit',
                    'table' => 'data_transfer_jobs',
                    'whereIn' => ['status' => ['uploaded', 'parsing', 'parsed', 'mapped', 'validating', 'validated', 'processing']],
                    'detail' => 'Uncommitted imports stop where they are; nothing is half-written.',
                ],
                [
                    'label' => 'Active export schedules',
                    'table' => 'data_transfer_schedules',
                    'where' => ['is_active' => true],
                    'detail' => 'Scheduled exports pause and resume if the module comes back.',
                ],
            ],
            'gym' => [[
                'label' => 'Active memberships',
                'table' => 'memberships',
                'where' => ['status' => 'active'],
                'detail' => 'Terms keep running; elapsed ones settle when the module returns.',
            ]],
            default => [],
        };

        $results = [];

        foreach ($checks as $check) {
            if (! Schema::hasTable($check['table'])) {
                continue;
            }

            $query = \Illuminate\Support\Facades\DB::table($check['table'])->where('company_id', $companyId);

            foreach ($check['where'] ?? [] as $column => $value) {
                if (Schema::hasColumn($check['table'], $column)) {
                    $query->where($column, $value);
                }
            }

            foreach ($check['whereIn'] ?? [] as $column => $values) {
                if (Schema::hasColumn($check['table'], $column)) {
                    $query->whereIn($column, $values);
                }
            }

            if (Schema::hasColumn($check['table'], 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $count = (int) $query->count();

            if ($count > 0) {
                $results[] = [
                    'label' => $check['label'],
                    'count' => $count,
                    'detail' => $check['detail'],
                ];
            }
        }

        return $results;
    }

    private function humanise(string $className): string
    {
        return trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $className));
    }
}
