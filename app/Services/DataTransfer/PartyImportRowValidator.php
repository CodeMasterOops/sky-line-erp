<?php

namespace App\Services\DataTransfer;

use App\Enums\PartyTypeEnum;
use Illuminate\Support\Facades\Validator;
use App\Services\DataTransfer\Import\ImportRowValidatorInterface;

class PartyImportRowValidator implements ImportRowValidatorInterface
{
    /**
     * Built-in synonyms for the contact type column.
     *
     * @var array<string, string>
     */
    private const TYPE_ALIASES = [
        'customer' => 'customer',
        'customers' => 'customer',
        'client' => 'customer',
        'buyer' => 'customer',
        'debtor' => 'customer',
        'supplier' => 'supplier',
        'suppliers' => 'supplier',
        'vendor' => 'supplier',
        'creditor' => 'supplier',
        'lead' => 'lead',
        'leads' => 'lead',
        'prospect' => 'lead',
    ];

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $context
     * @return array{normalized: array<string, mixed>, errors: list<string>, field_errors: list<array{field: string, value: string, message: string, suggestions: list<array{id: int, label: string}>}>, skip: bool}
     */
    public function validate(array $row, mixed $lookups = null, array $context = []): array
    {
        $errors = [];
        $fieldErrors = [];

        $partyLookups = $lookups instanceof PartyImportLookupCache ? $lookups : null;

        $type = $this->resolveType($row['type'] ?? null, $context, $partyLookups, $errors, $fieldErrors);

        if (empty($row['name'])) {
            $errors[] = 'Contact name is required.';
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
            'type' => $type,
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
            'field_errors' => $fieldErrors,
            'skip' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $errors
     * @param  list<array{field: string, value: string, message: string, suggestions: list<array{id: int, label: string}>}>  $fieldErrors
     */
    private function resolveType(
        mixed $value,
        array $context,
        ?PartyImportLookupCache $lookups,
        array &$errors,
        array &$fieldErrors,
    ): string {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            $default = $context['default_party_type'] ?? null;

            return $this->isValidType($default) ? $default : PartyTypeEnum::CUSTOMER->value;
        }

        $key = strtolower($raw);

        if ($lookups && ($alias = $lookups->resolveTypeAlias($raw)) && $this->isValidType($alias)) {
            return $alias;
        }

        if (isset(self::TYPE_ALIASES[$key])) {
            return self::TYPE_ALIASES[$key];
        }

        $suggestion = $this->closestType($key);
        $errors[] = "Contact type '{$raw}' is not recognised. Use 'customer', 'supplier' or 'lead'.";
        $fieldErrors[] = [
            'field' => 'type',
            'value' => $raw,
            'message' => "Unrecognised contact type. Did you mean '{$suggestion}'?",
            'suggestions' => [
                ['id' => 0, 'label' => PartyTypeEnum::CUSTOMER->value],
                ['id' => 0, 'label' => PartyTypeEnum::SUPPLIER->value],
                ['id' => 0, 'label' => PartyTypeEnum::LEAD->value],
            ],
        ];

        return $suggestion;
    }

    private function isValidType(mixed $value): bool
    {
        return is_string($value) && PartyTypeEnum::tryFrom($value) !== null;
    }

    private function closestType(string $value): string
    {
        $best = PartyTypeEnum::CUSTOMER->value;
        $bestScore = -1.0;

        foreach (PartyTypeEnum::cases() as $case) {
            similar_text($value, $case->value, $score);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $case->value;
            }
        }

        return $best;
    }
}
