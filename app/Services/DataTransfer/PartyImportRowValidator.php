<?php

namespace App\Services\DataTransfer;

use App\Enums\PartyTypeEnum;
use Illuminate\Support\Facades\Validator;
use App\Services\DataTransfer\Import\ImportRowValidatorInterface;

class PartyImportRowValidator implements ImportRowValidatorInterface
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $context
     * @return array{normalized: array<string, mixed>, errors: list<string>}
     */
    public function validate(array $row, mixed $lookups = null, array $context = []): array
    {
        $errors = [];

        $defaultType = $context['default_party_type'] ?? null;
        if ($defaultType !== PartyTypeEnum::SUPPLIER->value) {
            $errors[] = 'Only supplier import is enabled.';
        }

        if (empty($row['name'])) {
            $errors[] = 'Party name is required.';
        }

        $validator = Validator::make(
            [
                'email' => $row['email'] ?? null,
                'credit_limit' => $row['credit_limit'] ?? null,
            ],
            [
                'email' => ['nullable', 'email'],
                'credit_limit' => ['nullable', 'numeric'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $errors[] = $message;
            }
        }

        $isActive = true;
        if (isset($row['is_active']) && $row['is_active'] !== '') {
            $isActive = filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $normalized = [
            'type' => PartyTypeEnum::SUPPLIER->value,
            'name' => $row['name'] ?? null,
            'code' => $row['code'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'pan' => $row['pan'] ?? null,
            'address' => $row['address'] ?? null,
            'credit_limit' => isset($row['credit_limit']) && $row['credit_limit'] !== ''
                ? (float) $row['credit_limit']
                : 0,
            'is_active' => $isActive,
        ];

        return [
            'normalized' => $normalized,
            'errors' => $errors,
        ];
    }
}
