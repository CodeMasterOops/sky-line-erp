<?php

namespace Database\Seeders;

use App\Models\CompanyCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the initial industry catalogue from config/company_categories.php.
 *
 * Idempotent and non-destructive: a category that already exists keeps its
 * name, description and default modules, because from Phase 4 the Super Admin
 * edits these in the UI and a re-seed must not stomp those edits. Only missing
 * categories and missing default-module rows are created.
 */
class CompanyCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('company_categories', []) as $definition) {
            $category = CompanyCategory::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'icon' => $definition['icon'] ?? null,
                    'is_active' => $definition['is_active'] ?? true,
                    'is_default' => $definition['is_default'] ?? false,
                    'sort_order' => $definition['sort_order'] ?? 0,
                ],
            );

            foreach ($definition['modules'] ?? [] as $index => $moduleKey) {
                $category->modules()->firstOrCreate(
                    ['module_key' => $moduleKey],
                    ['is_default_enabled' => true, 'sort_order' => $index],
                );
            }
        }
    }
}
