<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 5 of docs/saas-modular-platform-and-gym-module-plan.md §5.
 *
 * A member is a 1:1 extension of a `party` (type = customer), not a parallel
 * entity: invoicing, receipts, AR ageing, statements, tags, notes and the CRM
 * timeline all key off party_id and come for free. Only gym-specific fields
 * live here.
 *
 * Both tables are Tier A (branch-owned) to match Party and Product — a chain
 * gets a per-branch member register and per-branch pricing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('member_code');
            $table->string('photo')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('occupation')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('medical_notes')->nullable();
            $table->date('joined_on');
            $table->string('status')->default('inactive');
            $table->string('source')->nullable();
            $table->foreignId('referred_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            // Optional link to an HR employee acting as trainer. Nullable so the
            // gym module never depends on the HR module being enabled.
            $table->foreignId('assigned_trainer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'member_code']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            // The service item this plan bills through. Carries the price, tax
            // linkage and revenue account, so a membership invoice is an
            // ordinary invoice with no gym-specific accounting.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('duration_unit')->default('month');
            $table->unsignedInteger('duration_value')->default(1);
            $table->string('preset')->nullable();
            $table->decimal('price', 18, 4)->default(0);
            $table->decimal('joining_fee', 18, 4)->default(0);
            $table->unsignedInteger('grace_days')->default(0);
            $table->unsignedInteger('max_freeze_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
        Schema::dropIfExists('members');
    }
};
