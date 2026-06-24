<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->boolean('is_basic')->default(false)->after('type');
            $table->string('percentage_base', 20)->default('gross_earnings')->after('calculation_type');
            $table->foreignId('contra_account_id')->nullable()->after('account_id')
                ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropForeign(['contra_account_id']);
            $table->dropColumn(['is_basic', 'percentage_base', 'contra_account_id']);
        });
    }
};
