<?php

namespace App\Services\DataTransfer;

use App\Models\Party;
use App\Enums\PartyTypeEnum;
use App\Models\DataTransferJob;
use Illuminate\Support\Facades\DB;
use App\Services\Party\PartyCodeGenerator;
use App\Services\DataTransfer\Import\ImportServiceInterface;

class PartyImportService implements ImportServiceInterface
{
    public function __construct(
        private PartyCodeGenerator $partyCodeGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{action: string, entity_id: int}
     */
    public function importRow(DataTransferJob $job, array $normalized, mixed $lookups = null): array
    {
        $duplicateMode = $job->options['duplicate_mode'] ?? 'update';
        $companyId = $job->company_id;

        $type = PartyTypeEnum::tryFrom((string) ($normalized['type'] ?? '')) ?? PartyTypeEnum::CUSTOMER;

        $existing = null;
        if (! empty($normalized['code'])) {
            $existing = Party::query()
                ->where('company_id', $companyId)
                ->where('code', $normalized['code'])
                ->first();
        }

        if ($existing && $duplicateMode === 'skip') {
            return ['action' => 'skipped', 'entity_id' => $existing->id];
        }

        if ($existing && $duplicateMode === 'create_only') {
            throw new \RuntimeException('Party code already exists.');
        }

        return DB::transaction(function () use ($job, $normalized, $existing, $companyId, $type) {
            $data = [
                'type' => $type,
                'name' => $normalized['name'],
                'code' => $normalized['code'] ?? null,
                'phone' => $normalized['phone'] ?? null,
                'email' => $normalized['email'] ?? null,
                'pan' => $normalized['pan'] ?? null,
                'address' => $normalized['address'] ?? null,
                'credit_limit' => $normalized['credit_limit'] ?? 0,
                'is_active' => (bool) ($normalized['is_active'] ?? true),
            ];

            $action = 'imported';

            if ($existing) {
                $existing->update($data);
                $party = $existing;
                $action = 'updated';
            } else {
                if (blank($data['code'])) {
                    $data['code'] = $this->partyCodeGenerator->generate(
                        $type,
                        $companyId,
                    );
                }

                $data['company_id'] = $companyId;
                $data['import_batch_id'] = $job->batch_id;
                $party = Party::create($data);
            }

            return [
                'action' => $action,
                'entity_id' => $party->id,
            ];
        });
    }
}
