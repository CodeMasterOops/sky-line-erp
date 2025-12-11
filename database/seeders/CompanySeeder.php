<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Enums\UserTypeEnum;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        if (Company::count() == 0) {
            $company = Company::create([
                'company_name' => 'Demo Company',
                'code' => 'CL-01',
                'legal_name' => 'Demo Company Pvt. Ltd.',
                'email' => 'democompany@admin.com',
            ]);

            User::create([
                'company_id' => $company->id,
                'name' => $company->company_name,
                'email' => $company->email,
                'password' => '1234567',
                'user_type' => UserTypeEnum::ADMIN->value,
            ]);
        }
    }
}
