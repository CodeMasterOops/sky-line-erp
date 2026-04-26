<?php

use App\Models\Bin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_transfer_items')) {
            return;
        }

        if (! Schema::hasColumn('stock_transfer_items', 'from_bin_id')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->foreignId('from_bin_id')->nullable()->after('quantity')->constrained('bins')->restrictOnDelete();
                $table->foreignId('to_bin_id')->nullable()->after('from_bin_id')->constrained('bins')->restrictOnDelete();
            });
        }

        $this->backfillBinIds();
        $this->dropTransferItemForeign('from_bin_id');
        $this->dropTransferItemForeign('to_bin_id');

        foreach (['from_bin_id', 'to_bin_id'] as $col) {
            try {
                Schema::table('stock_transfer_items', function (Blueprint $table) use ($col) {
                    $table->unsignedBigInteger($col)->nullable(false)->change();
                });
            } catch (\Throwable) {
            }
        }

        $this->addTransferItemForeign('from_bin_id');
        $this->addTransferItemForeign('to_bin_id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_transfer_items')) {
            return;
        }
        if (! Schema::hasColumn('stock_transfer_items', 'from_bin_id')) {
            return;
        }

        $this->dropTransferItemForeign('from_bin_id');
        $this->dropTransferItemForeign('to_bin_id');
        try {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->dropColumn(['from_bin_id', 'to_bin_id']);
            });
        } catch (\Throwable) {
        }
    }

    private function backfillBinIds(): void
    {
        $code = Bin::DEFAULT_CODE;

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('
                UPDATE stock_transfer_items AS sti
                INNER JOIN stock_transfers AS st ON st.id = sti.stock_transfer_id
                INNER JOIN bins AS bf
                    ON bf.company_id = st.company_id
                    AND bf.warehouse_id = st.from_warehouse_id
                    AND bf.code = ?
                INNER JOIN bins AS bt
                    ON bt.company_id = st.company_id
                    AND bt.warehouse_id = st.to_warehouse_id
                    AND bt.code = ?
                SET sti.from_bin_id = bf.id, sti.to_bin_id = bt.id
            ', [$code, $code]);

            return;
        }

        $items = DB::table('stock_transfer_items')->select('id', 'stock_transfer_id')->get();
        foreach ($items as $row) {
            $t = DB::table('stock_transfers')->where('id', $row->stock_transfer_id)->first();
            if (! $t) {
                continue;
            }
            $from = DB::table('bins')
                ->where('company_id', $t->company_id)
                ->where('warehouse_id', $t->from_warehouse_id)
                ->where('code', $code)
                ->value('id');
            $to = DB::table('bins')
                ->where('company_id', $t->company_id)
                ->where('warehouse_id', $t->to_warehouse_id)
                ->where('code', $code)
                ->value('id');
            if ($from && $to) {
                DB::table('stock_transfer_items')->where('id', $row->id)->update([
                    'from_bin_id' => $from,
                    'to_bin_id' => $to,
                ]);
            }
        }
    }

    private function dropTransferItemForeign(string $column): void
    {
        try {
            Schema::table('stock_transfer_items', function (Blueprint $table) use ($column) {
                $table->dropForeign([$column]);
            });
        } catch (\Throwable) {
        }
    }

    private function addTransferItemForeign(string $column): void
    {
        try {
            Schema::table('stock_transfer_items', function (Blueprint $table) use ($column) {
                $table->foreign($column)->references('id')->on('bins')->restrictOnDelete();
            });
        } catch (\Throwable) {
        }
    }
};
