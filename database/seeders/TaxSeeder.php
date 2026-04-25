<?php

namespace Database\Seeders;

use App\Models\Tax;
use App\Models\Company;
use App\Enums\TaxTypeEnum;
use App\Models\TaxTemplate;
use App\Enums\TdsCategoryEnum;
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
