<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 6 of docs/saas-modular-platform-and-gym-module-plan.md §5, §6.
 *
 * One row per term. A renewal never edits the previous term — it inserts a new
 * row chained through `renewed_from_id`, so membership history is immutable and
 * the invoice behind each term stays attached to the term it paid for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $table->string('membership_no');
            $table->date('start_date');
            // Stored rather than derived: expiry sweeps and "expiring soon"
            // lists both query it, and an index on a computed value is not
            // portable across the databases this app supports.
            $table->date('end_date');
            $table->string('status')->default('active');
            $table->decimal('price', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('joining_fee', 18, 4)->default(0);
            $table->decimal('payable_amount', 18, 4)->default(0);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('renewed_from_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->unsignedInteger('freeze_days_used')->default(0);
            // Day-offsets already notified, so re-running the reminder sweep on
            // the same day sends nothing twice.
            $table->json('reminders_sent')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'membership_no']);
            $table->index(['company_id', 'branch_id', 'status', 'end_date']);
            $table->index(['member_id', 'start_date']);
        });

        Schema::table('company_notification_settings', function (Blueprint $table) {
            $table->boolean('membership_expiry_reminder')->default(true);
            $table->json('membership_expiry_reminder_days')->nullable();
            $table->boolean('membership_expired_alert')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('company_notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'membership_expiry_reminder',
                'membership_expiry_reminder_days',
                'membership_expired_alert',
            ]);
        });

        Schema::dropIfExists('memberships');
    }
};
