<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 20);
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('pan', 20)->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
        });

        // Add nullable branch_id to key transaction tables
        foreach (['invoices', 'bills', 'receipts', 'payments', 'journals', 'expenses', 'stock_transfers'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'bills', 'receipts', 'payments', 'journals', 'expenses', 'stock_transfers'] as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'branch_id')) {
                Schema::table($tbl, fn (Blueprint $t) => $t->dropForeign(['branch_id']));
                Schema::table($tbl, fn (Blueprint $t) => $t->dropColumn('branch_id'));
            }
        }
        Schema::dropIfExists('branches');
    }
};
