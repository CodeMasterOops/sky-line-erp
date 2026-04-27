<?php

use Illuminate\Support\Facades\Schema;
use App\Enums\AccountingPeriodStatusEnum;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('period_number');
            $table->string('period_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default(AccountingPeriodStatusEnum::OPEN->value);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year_id', 'period_number'], 'acct_periods_company_fy_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
