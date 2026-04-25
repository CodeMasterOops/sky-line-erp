<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('is_active')
                ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
