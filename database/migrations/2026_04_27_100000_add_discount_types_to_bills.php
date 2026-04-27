<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Enums\AmountOrPercentDiscountTypeEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('order_discount_type', 16)
                ->default(AmountOrPercentDiscountTypeEnum::Fixed->value)
                ->after('remarks');
            $table->decimal('order_discount_value', 12, 2)->nullable()->after('order_discount_type');
            $table->decimal('order_discount_amount', 12, 2)->default(0)->after('order_discount_value');
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->string('line_discount_type', 16)
                ->default(AmountOrPercentDiscountTypeEnum::Fixed->value)
                ->after('rate');
            $table->decimal('line_discount_value', 12, 2)->nullable()->after('line_discount_type');
        });

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::table('bill_items')->orderBy('id')->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('bill_items')->where('id', $row->id)->update([
                        'line_discount_type' => AmountOrPercentDiscountTypeEnum::Fixed->value,
                        'line_discount_value' => $row->discount_amount ?? 0,
                    ]);
                }
            });
        } else {
            $items = DB::table('bill_items')->get();
            foreach ($items as $row) {
                DB::table('bill_items')->where('id', $row->id)->update([
                    'line_discount_type' => AmountOrPercentDiscountTypeEnum::Fixed->value,
                    'line_discount_value' => $row->discount_amount ?? 0,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn(['line_discount_type', 'line_discount_value']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['order_discount_type', 'order_discount_value', 'order_discount_amount']);
        });
    }
};
