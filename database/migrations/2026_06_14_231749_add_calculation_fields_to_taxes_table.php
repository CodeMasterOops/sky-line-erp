<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->string('calculation_type', 20)->default('percentage')->after('rate');
            $table->boolean('is_inclusive')->default(false)->after('calculation_type');
            $table->boolean('is_compound')->default(false)->after('is_inclusive');
            $table->unsignedInteger('sequence')->default(1)->after('is_compound');
            $table->foreignId('gl_account_id')->nullable()->after('sequence')
                ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gl_account_id');
            $table->dropColumn(['calculation_type', 'is_inclusive', 'is_compound', 'sequence']);
        });
    }
};
