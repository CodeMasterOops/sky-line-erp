<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_tax_exemptions', function (Blueprint $table) {
            $table->softDeletes()->after('valid_to');
        });
    }

    public function down(): void
    {
        Schema::table('party_tax_exemptions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
