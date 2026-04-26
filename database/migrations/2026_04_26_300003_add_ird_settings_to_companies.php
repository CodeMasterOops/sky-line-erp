<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds IRD Electronic Billing System (EBS) credentials to companies.
 * Each company must register their fiscal device with IRD to get these.
 *
 * ird_username:       IRD-issued username for EBS API
 * ird_password:       IRD-issued password (stored encrypted)
 * ird_branch_office:  Branch office code assigned by IRD
 * ird_unit_name:      Business unit name as registered with IRD
 * ird_fiscal_device:  Fiscal device serial number
 * ird_ebs_enabled:    Whether IRD sync is active for this company
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('ird_username')->nullable()->after('pan');
            $table->string('ird_password')->nullable()->after('ird_username')
                ->comment('Encrypted IRD EBS password');
            $table->string('ird_branch_office', 50)->nullable()->after('ird_password');
            $table->string('ird_unit_name')->nullable()->after('ird_branch_office');
            $table->string('ird_fiscal_device', 100)->nullable()->after('ird_unit_name');
            $table->boolean('ird_ebs_enabled')->default(false)->after('ird_fiscal_device');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'ird_username', 'ird_password', 'ird_branch_office',
                'ird_unit_name', 'ird_fiscal_device', 'ird_ebs_enabled',
            ]);
        });
    }
};
