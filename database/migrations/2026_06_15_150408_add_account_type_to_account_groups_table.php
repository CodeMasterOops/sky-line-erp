<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_groups', function (Blueprint $table) {
            // Nullable so existing groups are unaffected; set explicitly to enable
            // type-based classification in ClosingEntryService and cashFlow().
            $table->string('account_type')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('account_groups', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });
    }
};
