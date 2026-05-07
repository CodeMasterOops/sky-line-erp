<?php

namespace App\Services;

use Database\Seeders\TaxSeeder;
use Database\Seeders\CompanyCatalogSeeder;
use App\Services\Accounting\CoaInsertService;

class SetCompanyDefaultDataService
{
    public static function setData($company): void
    {
        (new CoaInsertService($company))->saveCoaData();
        CompanyBootstrapService::runForCompany($company->id);
        TaxSeeder::seedForCompany($company->id);
        CompanyCatalogSeeder::seedForCompany($company->id);
    }
}
