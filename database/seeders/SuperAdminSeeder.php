<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (SuperAdmin::count() == 0) {
            SuperAdmin::create([
                'name' => 'Super Admin',
                'email' => 'super@admin.com',
                'password' => '1234567',
            ]);
        }
    }
}
