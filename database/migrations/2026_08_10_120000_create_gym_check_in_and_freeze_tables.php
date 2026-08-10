<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 7 of docs/saas-modular-platform-and-gym-module-plan.md §5.
 *
 * Visits and freezes. A freeze is a child of the term it pauses: resuming
 * extends that term's end date by the days lost, which is why the days are
 * recorded rather than recomputed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // The term the visit counted against, when there was one. Nullable
            // so a lapsed member can still be recorded walking in.
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->string('method')->default('manual');
            $table->string('device_ref')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'branch_id', 'checked_in_at']);
            $table->index(['member_id', 'checked_in_at']);
        });

        Schema::create('membership_freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            // Null while the freeze is running; set when the member resumes.
            $table->date('to_date')->nullable();
            $table->unsignedInteger('days')->default(0);
            $table->string('reason')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['membership_id', 'from_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_freezes');
        Schema::dropIfExists('member_check_ins');
    }
};
