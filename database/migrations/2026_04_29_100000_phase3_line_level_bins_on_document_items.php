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
        $this->addBinColumn('bill_items', 'warehouse_id', function (Blueprint $table) {
            $table->foreignId('bin_id')->nullable()->after('warehouse_id')->constrained('bins')->restrictOnDelete();
        });

        $this->addBinColumn('invoice_items', 'warehouse_id', function (Blueprint $table) {
            $table->foreignId('bin_id')->nullable()->after('warehouse_id')->constrained('bins')->restrictOnDelete();
        });

        $this->addBinColumn('stock_adjustment_items', 'unit_cost', function (Blueprint $table) {
            $table->foreignId('bin_id')->nullable()->after('unit_cost')->constrained('bins')->restrictOnDelete();
        });

        $this->addBinColumn('credit_note_items', 'warehouse_id', function (Blueprint $table) {
            $table->foreignId('bin_id')->nullable()->after('warehouse_id')->constrained('bins')->restrictOnDelete();
        });

        $this->addBinColumn('debit_note_items', 'warehouse_id', function (Blueprint $table) {
            $table->foreignId('bin_id')->nullable()->after('warehouse_id')->constrained('bins')->restrictOnDelete();
        });

        $this->backfillBillItems();
        $this->backfillInvoiceItems();
        $this->backfillStockAdjustmentItems();
        $this->backfillCreditNoteItems();
        $this->backfillDebitNoteItems();

        foreach (['bill_items', 'invoice_items', 'stock_adjustment_items', 'credit_note_items', 'debit_note_items'] as $table) {
            $this->makeBinIdNotNull($table);
        }
    }

    public function down(): void
    {
        foreach (['bill_items', 'invoice_items', 'stock_adjustment_items', 'credit_note_items', 'debit_note_items'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'bin_id')) {
                continue;
            }
            $this->dropBinForeign($table);
            try {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('bin_id');
                });
            } catch (\Throwable) {
            }
        }
    }

    private function addBinColumn(string $table, string $afterColumn, callable $add): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'bin_id')) {
            return;
        }
        if (! Schema::hasColumn($table, $afterColumn)) {
            Schema::table($table, $add);

            return;
        }
        Schema::table($table, $add);
    }

    private function resolveDefaultBinId(int $companyId, int $warehouseId): ?int
    {
        $id = DB::table('bins')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('code', Bin::DEFAULT_CODE)
            ->whereNull('deleted_at')
            ->value('id');
        if ($id) {
            return (int) $id;
        }
        $fallback = DB::table('bins')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        return $fallback ? (int) $fallback : null;
    }

    private function backfillBillItems(): void
    {
        if (! Schema::hasTable('bill_items') || ! Schema::hasColumn('bill_items', 'bin_id')) {
            return;
        }
        $rows = DB::table('bill_items as bi')
            ->join('bills as b', 'b.id', '=', 'bi.bill_id')
            ->whereNull('bi.deleted_at')
            ->select('bi.id', 'b.company_id', 'bi.warehouse_id')
            ->get();
        foreach ($rows as $row) {
            $binId = $this->resolveDefaultBinId((int) $row->company_id, (int) $row->warehouse_id);
            if ($binId) {
                DB::table('bill_items')->where('id', $row->id)->update(['bin_id' => $binId]);
            }
        }
    }

    private function backfillInvoiceItems(): void
    {
        if (! Schema::hasTable('invoice_items') || ! Schema::hasColumn('invoice_items', 'bin_id')) {
            return;
        }
        $rows = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->whereNull('ii.deleted_at')
            ->select('ii.id', 'i.company_id', 'ii.warehouse_id')
            ->get();
        foreach ($rows as $row) {
            $binId = $this->resolveDefaultBinId((int) $row->company_id, (int) $row->warehouse_id);
            if ($binId) {
                DB::table('invoice_items')->where('id', $row->id)->update(['bin_id' => $binId]);
            }
        }
    }

    private function backfillStockAdjustmentItems(): void
    {
        if (! Schema::hasTable('stock_adjustment_items') || ! Schema::hasColumn('stock_adjustment_items', 'bin_id')) {
            return;
        }
        $rows = DB::table('stock_adjustment_items as sai')
            ->join('stock_adjustments as sa', 'sa.id', '=', 'sai.stock_adjustment_id')
            ->whereNull('sai.deleted_at')
            ->select('sai.id', 'sa.company_id', 'sa.warehouse_id')
            ->get();
        foreach ($rows as $row) {
            $binId = $this->resolveDefaultBinId((int) $row->company_id, (int) $row->warehouse_id);
            if ($binId) {
                DB::table('stock_adjustment_items')->where('id', $row->id)->update(['bin_id' => $binId]);
            }
        }
    }

    private function backfillCreditNoteItems(): void
    {
        if (! Schema::hasTable('credit_note_items') || ! Schema::hasColumn('credit_note_items', 'bin_id')) {
            return;
        }
        $rows = DB::table('credit_note_items as cni')
            ->join('credit_notes as cn', 'cn.id', '=', 'cni.credit_note_id')
            ->whereNull('cni.deleted_at')
            ->select('cni.id', 'cn.company_id', 'cni.warehouse_id')
            ->get();
        foreach ($rows as $row) {
            $binId = $this->resolveDefaultBinId((int) $row->company_id, (int) $row->warehouse_id);
            if ($binId) {
                DB::table('credit_note_items')->where('id', $row->id)->update(['bin_id' => $binId]);
            }
        }
    }

    private function backfillDebitNoteItems(): void
    {
        if (! Schema::hasTable('debit_note_items') || ! Schema::hasColumn('debit_note_items', 'bin_id')) {
            return;
        }
        $rows = DB::table('debit_note_items as dni')
            ->join('debit_notes as dn', 'dn.id', '=', 'dni.debit_note_id')
            ->whereNull('dni.deleted_at')
            ->select('dni.id', 'dn.company_id', 'dni.warehouse_id')
            ->get();
        foreach ($rows as $row) {
            $binId = $this->resolveDefaultBinId((int) $row->company_id, (int) $row->warehouse_id);
            if ($binId) {
                DB::table('debit_note_items')->where('id', $row->id)->update(['bin_id' => $binId]);
            }
        }
    }

    private function makeBinIdNotNull(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'bin_id')) {
            return;
        }
        $this->dropBinForeign($table);
        try {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('bin_id')->nullable(false)->change();
            });
        } catch (\Throwable) {
        }
        try {
            Schema::table($table, function (Blueprint $t) {
                $t->foreign('bin_id')->references('id')->on('bins')->restrictOnDelete();
            });
        } catch (\Throwable) {
        }
    }

    private function dropBinForeign(string $table): void
    {
        try {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['bin_id']);
            });
        } catch (\Throwable) {
        }
    }
};
