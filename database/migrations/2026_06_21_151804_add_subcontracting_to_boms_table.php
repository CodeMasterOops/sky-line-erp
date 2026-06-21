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
        Schema::table('boms', function (Blueprint $table) {
            // Subcontracting: the output is (partly) produced by an external vendor for a
            // standard service charge per output unit, absorbed into WIP like overhead.
            $table->boolean('is_subcontracted')->default(false)->after('is_backflush');
            $table->decimal('subcontract_charge', 15, 4)->default(0)->after('is_subcontracted');
            $table->foreignId('subcontractor_party_id')->nullable()->after('subcontract_charge')
                ->constrained('parties')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcontractor_party_id');
            $table->dropColumn(['is_subcontracted', 'subcontract_charge']);
        });
    }
};
