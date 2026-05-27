<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\EntityCodeType;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use App\Services\EntityCodeGenerator;

trait GeneratesEntityCode
{
    protected function resolveCompanyId(): int
    {
        return TenantService::companyId()
            ?? (int) auth('admin')->user()->company_id;
    }

    protected function nextCodeResponse(EntityCodeType $type): JsonResponse
    {
        return response()->json([
            'data' => [
                'code' => app(EntityCodeGenerator::class)->generateForType(
                    $type,
                    $this->resolveCompanyId(),
                ),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assignEntityCode(array &$data, EntityCodeType $type): void
    {
        $column = $type->column();

        if (blank($data[$column] ?? null)) {
            $data[$column] = app(EntityCodeGenerator::class)->generateForType(
                $type,
                $this->resolveCompanyId(),
            );
        }
    }
}
