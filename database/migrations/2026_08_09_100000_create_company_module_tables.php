<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 1 of docs/saas-modular-platform-and-gym-module-plan.md §3.4.
 *
 * Definitions of what a module *is* stay in config/modules.php; these tables
 * only hold state: which company runs which module, which industry category
 * pre-selects which defaults, and an append-only audit of every change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_category_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_category_id')->constrained()->cascadeOnDelete();
            $table->string('module_key');
            $table->boolean('is_default_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_category_id', 'module_key']);
        });

        Schema::create('company_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('module_key');
            $table->boolean('is_enabled')->default(true);
            $table->string('source')->default('manual');
            $table->json('settings')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->string('updated_by_type')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'module_key']);
            $table->index(['company_id', 'is_enabled']);
        });

        Schema::create('company_module_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('module_key');
            $table->string('action');
            $table->string('reason')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'module_key', 'created_at']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('company_category_id')
                ->nullable()
                ->after('code')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('plans', function (Blueprint $table) {
            // NULL = the plan entitles every module. A list caps what the
            // company may switch on; it never deletes anything already there.
            $table->json('modules')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('modules');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_category_id');
        });

        Schema::dropIfExists('company_module_events');
        Schema::dropIfExists('company_modules');
        Schema::dropIfExists('company_category_modules');
        Schema::dropIfExists('company_categories');
    }
};
