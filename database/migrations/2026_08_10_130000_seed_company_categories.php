<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the industry catalogue on deploy.
 *
 * CompanyCategorySeeder covers fresh installs and local work, but choosing a
 * category is mandatory when creating a company — so an existing deployment
 * that never ran the seeder would be unable to create one at all. This puts the
 * catalogue in place as part of the upgrade.
 *
 * Only missing slugs are inserted, so it is safe to re-run and never overwrites
 * a category the Super Admin has edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (config('company_categories', []) as $definition) {
            $exists = DB::table('company_categories')->where('slug', $definition['slug'])->exists();

            if ($exists) {
                continue;
            }

            $categoryId = DB::table('company_categories')->insertGetId([
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'description' => $definition['description'] ?? null,
                'icon' => $definition['icon'] ?? null,
                'is_active' => true,
                'is_default' => $definition['is_default'] ?? false,
                'sort_order' => $definition['sort_order'] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($definition['modules'] ?? [] as $index => $moduleKey) {
                DB::table('company_category_modules')->insert([
                    'company_category_id' => $categoryId,
                    'module_key' => $moduleKey,
                    'is_default_enabled' => true,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Categories are reference data the Super Admin owns once seeded;
        // companies point at them. Removing them on rollback would orphan those
        // references, so this is deliberately one-way.
    }
};
