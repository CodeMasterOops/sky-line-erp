<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Starter — ideal for startups & shops',
                'price_monthly' => 999,
                'price_yearly' => 9999,
                'branch_limit' => 1,
                'features' => [
                    'Ideal for startups & shops',
                    '1 branch location',
                    'Purchase & sales management',
                    'Core inventory & accounting',
                    '5-day free demo included',
                    'Save Rs. 1,989 with yearly billing',
                ],
                'is_active' => true,
                'is_default' => true,
                'is_recommended' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'description' => 'Growth — ideal for growing SMEs',
                'price_monthly' => 1999,
                'price_yearly' => 19999,
                'branch_limit' => 2,
                'features' => [
                    'Ideal for growing SMEs',
                    'Up to 2 branch locations',
                    'Multi-branch sync',
                    'Everything in Basic',
                    'Advanced reporting',
                    '7-day free demo included',
                    'Save Rs. 3,989 with yearly billing',
                ],
                'is_active' => true,
                'is_default' => false,
                'is_recommended' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Advanced',
                'slug' => 'advanced',
                'description' => 'Established — ideal for large organizations',
                'price_monthly' => 2999,
                'price_yearly' => 29999,
                'branch_limit' => 3,
                'features' => [
                    'Ideal for large organizations',
                    'Up to 3 branch locations',
                    'HR & payroll module',
                    'Performance & attendance tracking',
                    'Everything in Standard',
                    '8-day free demo included',
                    'Save Rs. 5,989 with yearly billing',
                ],
                'is_active' => true,
                'is_default' => false,
                'is_recommended' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Enterprise — ideal for corporations',
                'price_monthly' => 26999,
                'price_yearly' => 299999,
                'branch_limit' => 15,
                'features' => [
                    'Ideal for corporations',
                    'Up to 15 branch locations',
                    'Full scalability across modules',
                    'Everything in Advanced',
                    'Priority support & onboarding',
                    '10-day free demo included',
                    'Save Rs. 23,989 with yearly billing',
                ],
                'is_active' => true,
                'is_default' => false,
                'is_recommended' => false,
                'sort_order' => 4,
            ],
        ];

        Plan::query()->update(['is_default' => false]);

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        Plan::query()
            ->whereNotIn('slug', collect($plans)->pluck('slug'))
            ->update(['is_active' => false, 'is_default' => false]);
    }
}
