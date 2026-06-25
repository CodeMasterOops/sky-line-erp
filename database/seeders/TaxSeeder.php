<?php

namespace Database\Seeders;

use App\Models\Tax;
use App\Models\Company;
use App\Models\TaxGroup;
use App\Enums\TaxTypeEnum;
use App\Models\TaxTemplate;
use App\Enums\TdsCategoryEnum;
use App\Models\TaxGroupMember;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    /**
     * Seed default system taxes for all companies that don't yet have them.
     * Reads from the tax_templates table if records exist; falls back to hardcoded Nepal defaults.
     */
    public function run(): void
    {
        Company::all()->each(function (Company $company) {
            $this->seedForCompany($company->id);
            $this->seedGroupsForCompany($company->id);
        });
    }

    public static function seedForCompany(int $companyId): void
    {
        if (Tax::where('company_id', $companyId)->where('is_system', true)->exists()) {
            return;
        }

        $defaultTaxes = self::getDefaults();

        foreach ($defaultTaxes as $taxData) {
            Tax::create(['company_id' => $companyId] + $taxData);
        }
    }

    public static function seedGroupsForCompany(int $companyId): void
    {
        if (TaxGroup::where('company_id', $companyId)->where('is_system', true)->exists()) {
            return;
        }

        $vatStandard = Tax::where('company_id', $companyId)->where('type', TaxTypeEnum::VAT_STANDARD)->first();
        $vatExempt = Tax::where('company_id', $companyId)->where('type', TaxTypeEnum::VAT_EXEMPT)->first();
        $vatZeroRated = Tax::where('company_id', $companyId)->where('type', TaxTypeEnum::VAT_ZERO_RATED)->first();

        /** @var \Illuminate\Support\Collection<string, Tax> $tdsMap */
        $tdsMap = Tax::where('company_id', $companyId)
            ->where('type', TaxTypeEnum::TDS)
            ->whereNotNull('tds_category')
            ->get()
            ->keyBy(fn (Tax $t) => $t->tds_category->value);

        $groups = self::getDefaultGroupDefinitions($vatStandard, $vatExempt, $vatZeroRated, $tdsMap);

        foreach ($groups as $groupData) {
            if (empty($groupData['members'])) {
                continue;
            }

            $group = TaxGroup::create([
                'company_id' => $companyId,
                'name' => $groupData['name'],
                'description' => $groupData['description'],
                'is_active' => true,
                'is_system' => true,
                'is_default' => $groupData['is_default'] ?? false,
            ]);

            foreach ($groupData['members'] as $member) {
                TaxGroupMember::create([
                    'tax_group_id' => $group->id,
                    'tax_id' => $member['tax']->id,
                    'sequence' => $member['sequence'],
                ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Tax>  $tdsMap  Keyed by TdsCategoryEnum value
     */
    private static function getDefaultGroupDefinitions(
        ?Tax $vatStandard,
        ?Tax $vatExempt,
        ?Tax $vatZeroRated,
        \Illuminate\Support\Collection $tdsMap,
    ): array {
        $tds = fn (string $cat) => $tdsMap->get($cat);

        $groups = [];

        if ($vatStandard) {
            $groups[] = [
                'name' => 'VAT 13%',
                'description' => 'Standard VAT at 13% for taxable sales and purchases.',
                'is_default' => true,
                'members' => [['tax' => $vatStandard, 'sequence' => 1]],
            ];
        }

        if ($vatExempt) {
            $groups[] = [
                'name' => 'VAT Exempt',
                'description' => 'Applies to VAT-exempt goods and services.',
                'members' => [['tax' => $vatExempt, 'sequence' => 1]],
            ];
        }

        if ($vatZeroRated) {
            $groups[] = [
                'name' => 'VAT Zero Rated',
                'description' => 'Applies to zero-rated exports and supplies.',
                'members' => [['tax' => $vatZeroRated, 'sequence' => 1]],
            ];
        }

        // VAT + TDS combined groups (purchase side)
        $vatTdsCombinations = [
            [TdsCategoryEnum::SERVICE_VAT_BILL->value,       'VAT + TDS – Service (1.5%)',          'VAT and TDS for services with a VAT bill (contractor/vendor).'],
            [TdsCategoryEnum::SERVICE_PAN_BILL->value,       'VAT + TDS – Service (PAN Bill 15%)',  'VAT and TDS for services billed without VAT (PAN bill only).'],
            [TdsCategoryEnum::CONTRACT_VAT_REGISTERED->value, 'VAT + TDS – Contract (1.5%)',         'VAT and TDS for contract payments to VAT-registered parties.'],
            [TdsCategoryEnum::RENT_PROPERTY->value,          'VAT + TDS – Rent Property (10%)',     'VAT and TDS for property rental payments.'],
            [TdsCategoryEnum::RENT_VEHICLE_VAT->value,       'VAT + TDS – Vehicle Hire (1.5%)',     'VAT and TDS for vehicle hire with VAT bill.'],
        ];

        foreach ($vatTdsCombinations as [$category, $name, $description]) {
            $tdsTax = $tds($category);

            if ($tdsTax && $vatStandard) {
                $groups[] = [
                    'name' => $name,
                    'description' => $description,
                    'members' => [
                        ['tax' => $tdsTax,      'sequence' => 1],
                        ['tax' => $vatStandard, 'sequence' => 2],
                    ],
                ];
            }
        }

        // TDS-only groups (no VAT on these purchases)
        $tdsOnlyCombinations = [
            [TdsCategoryEnum::SERVICE_PAN_BILL->value, 'TDS – Service (PAN Bill 15%)', 'TDS only for services billed without VAT.'],
            [TdsCategoryEnum::RENT_PROPERTY->value,    'TDS – Rent Property (10%)',    'TDS only for property rent (no VAT applicable).'],
            [TdsCategoryEnum::DIVIDEND->value,         'TDS – Dividend (5%)',          'TDS for dividend distributions.'],
        ];

        foreach ($tdsOnlyCombinations as [$category, $name, $description]) {
            $tdsTax = $tds($category);

            if ($tdsTax) {
                $groups[] = [
                    'name' => $name,
                    'description' => $description,
                    'members' => [['tax' => $tdsTax, 'sequence' => 1]],
                ];
            }
        }

        return $groups;
    }

    /**
     * Returns tax data from tax_templates table if populated, otherwise uses hardcoded Nepal defaults.
     */
    public static function getDefaults(): array
    {
        $templates = TaxTemplate::where('is_default', true)->get();

        if ($templates->isNotEmpty()) {
            return $templates->map(fn ($t) => [
                'name' => $t->name,
                'rate' => $t->rate,
                'type' => $t->type?->value,
                'tds_category' => $t->tds_category?->value,
                'is_system' => true,
            ])->toArray();
        }

        // Hardcoded Nepal defaults (used when no templates are configured yet)
        return [
            ['name' => 'VAT 13%',                          'rate' => 13.0,  'type' => TaxTypeEnum::VAT_STANDARD->value,                   'tds_category' => null,                                     'is_system' => true],
            ['name' => 'VAT Exempt',                        'rate' => 0.0,   'type' => TaxTypeEnum::VAT_EXEMPT->value,                     'tds_category' => null,                                     'is_system' => true],
            ['name' => 'VAT Zero Rated',                    'rate' => 0.0,   'type' => TaxTypeEnum::VAT_ZERO_RATED->value,                 'tds_category' => null,                                     'is_system' => true],
            ['name' => 'TDS – Service (VAT Bill) 1.5%',    'rate' => TdsCategoryEnum::SERVICE_VAT_BILL->rate(),         'type' => TaxTypeEnum::TDS->value, 'tds_category' => TdsCategoryEnum::SERVICE_VAT_BILL->value,         'is_system' => true],
            ['name' => 'TDS – Service (PAN Bill) 15%',     'rate' => TdsCategoryEnum::SERVICE_PAN_BILL->rate(),         'type' => TaxTypeEnum::TDS->value, 'tds_category' => TdsCategoryEnum::SERVICE_PAN_BILL->value,         'is_system' => true],
            ['name' => 'TDS – Contract (VAT Reg.) 1.5%',   'rate' => TdsCategoryEnum::CONTRACT_VAT_REGISTERED->rate(),  'type' => TaxTypeEnum::TDS->value, 'tds_category' => TdsCategoryEnum::CONTRACT_VAT_REGISTERED->value,  'is_system' => true],
            ['name' => 'TDS – Rent (Property) 10%',        'rate' => TdsCategoryEnum::RENT_PROPERTY->rate(),            'type' => TaxTypeEnum::TDS->value, 'tds_category' => TdsCategoryEnum::RENT_PROPERTY->value,            'is_system' => true],
            ['name' => 'TDS – Vehicle Hire (VAT Bill) 1.5%', 'rate' => TdsCategoryEnum::RENT_VEHICLE_VAT->rate(),        'type' => TaxTypeEnum::TDS->value, 'tds_category' => TdsCategoryEnum::RENT_VEHICLE_VAT->value,         'is_system' => true],
            ['name' => 'TDS – Dividend 5%',                'rate' => TdsCategoryEnum::DIVIDEND->rate(),                 'type' => TaxTypeEnum::TDS->value, 'tds_category' => TdsCategoryEnum::DIVIDEND->value,                 'is_system' => true],
        ];
    }
}
