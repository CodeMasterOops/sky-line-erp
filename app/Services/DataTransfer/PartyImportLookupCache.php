<?php

namespace App\Services\DataTransfer;

use App\Models\ImportValueAlias;
use Illuminate\Support\Facades\Cache;

class PartyImportLookupCache
{
    /**
     * Resolved party type aliases (lowercased source => 'customer'|'supplier'|'lead').
     *
     * @var array<string, string>
     */
    private array $typeAliases = [];

    public function __construct(private int $companyId) {}

    public static function forCompany(int $companyId): self
    {
        return Cache::remember(
            "dt:party-lookups:{$companyId}",
            300,
            fn () => (new self($companyId))->warm()
        );
    }

    public static function forget(int $companyId): void
    {
        Cache::forget("dt:party-lookups:{$companyId}");
    }

    public function warm(): self
    {
        ImportValueAlias::query()
            ->where('company_id', $this->companyId)
            ->where('entity_type', 'party')
            ->where('field', 'type')
            ->get()
            ->each(function (ImportValueAlias $alias): void {
                if ($alias->target_value !== null) {
                    $this->typeAliases[strtolower(trim($alias->source_value))] = strtolower($alias->target_value);
                }
            });

        return $this;
    }

    public function resolveTypeAlias(string $value): ?string
    {
        return $this->typeAliases[strtolower(trim($value))] ?? null;
    }
}
