<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouses') || ! Schema::hasTable('bins')) {
            return;
        }

        $this->ensureDefaultBinsExist();

        if (Schema::hasTable('stock_layers') && ! Schema::hasColumn('stock_layers', 'bin_id')) {
            Schema::table('stock_layers', function (Blueprint $table) {
                $table->foreignId('bin_id')->nullable()->after('warehouse_id')->constrained('bins')->restrictOnDelete();
            });
        }

        if (Schema::hasTable('stock_layers') && Schema::hasColumn('stock_layers', 'bin_id')) {
            $this->backfillStockLayersBinId();
            $this->tryDropBinForeign('stock_layers');
            Schema::table('stock_layers', function (Blueprint $table) {
                $table->unsignedBigInteger('bin_id')->nullable(false)->change();
            });
            $this->tryAddBinForeign('stock_layers');
        }

        if (Schema::hasTable('stocks')) {
            if (! Schema::hasColumn('stocks', 'bin_id')) {
                Schema::table('stocks', function (Blueprint $table) {
                    $table->foreignId('bin_id')->nullable()->after('warehouse_id')->constrained('bins')->restrictOnDelete();
                });
            }

            $this->tryDropStocksOldUnique();
            $this->backfillStocksBinId();
            $this->tryDropBinForeign('stocks');
            if (Schema::hasColumn('stocks', 'bin_id')) {
                Schema::table('stocks', function (Blueprint $table) {
                    $table->unsignedBigInteger('bin_id')->nullable(false)->change();
                });
            }
            $this->tryAddBinForeign('stocks');

            try {
                Schema::table('stocks', function (Blueprint $table) {
                    if (! $this->hasStocksNewUnique()) {
                        $table->unique(
                            ['company_id', 'product_variant_id', 'warehouse_id', 'bin_id'],
                            'stocks_company_variant_warehouse_bin_unique'
                        );
                    }
                });
            } catch (\Throwable) {
            }
        }

        if (Schema::hasTable('stock_movements') && ! Schema::hasColumn('stock_movements', 'bin_id')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->foreignId('bin_id')->nullable()->after('warehouse_id')->constrained('bins')->restrictOnDelete();
            });
        }

        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'bin_id')) {
            $this->backfillStockMovementsBinId();
            $this->tryDropBinForeign('stock_movements');
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->unsignedBigInteger('bin_id')->nullable(false)->change();
            });
            $this->tryAddBinForeign('stock_movements');
        }

        if (Schema::hasTable('stock_layers')) {
            try {
                Schema::table('stock_layers', function (Blueprint $table) {
                    if (! $this->hasIndexByName('stock_layers', 'stock_layers_c_v_w_b_idx')) {
                        $table->index(
                            ['company_id', 'product_variant_id', 'warehouse_id', 'bin_id'],
                            'stock_layers_c_v_w_b_idx'
                        );
                    }
                });
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_layers')) {
            $this->tryDropIndex('stock_layers', 'stock_layers_c_v_w_b_idx');
        }

        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'bin_id')) {
            try {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->dropForeign(['bin_id']);
                });
            } catch (\Throwable) {
            }
            try {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->dropColumn('bin_id');
                });
            } catch (\Throwable) {
            }
        }

        if (Schema::hasTable('stocks') && Schema::hasColumn('stocks', 'bin_id')) {
            $this->tryDropStocksNewUnique();
            try {
                Schema::table('stocks', function (Blueprint $table) {
                    $table->dropForeign(['bin_id']);
                });
            } catch (\Throwable) {
            }
            try {
                Schema::table('stocks', function (Blueprint $table) {
                    $table->dropColumn('bin_id');
                });
            } catch (\Throwable) {
            }
            if (! $this->hasStocksOldUnique()) {
                try {
                    Schema::table('stocks', function (Blueprint $table) {
                        $table->unique(
                            ['company_id', 'product_variant_id', 'warehouse_id'],
                            'stocks_company_variant_warehouse_unique'
                        );
                    });
                } catch (\Throwable) {
                }
            }
        }

        if (Schema::hasTable('stock_layers') && Schema::hasColumn('stock_layers', 'bin_id')) {
            try {
                Schema::table('stock_layers', function (Blueprint $table) {
                    $table->dropForeign(['bin_id']);
                });
            } catch (\Throwable) {
            }
            try {
                Schema::table('stock_layers', function (Blueprint $table) {
                    $table->dropColumn('bin_id');
                });
            } catch (\Throwable) {
            }
        }
    }

    private function hasStocksNewUnique(): bool
    {
        return $this->hasIndexByName('stocks', 'stocks_company_variant_warehouse_bin_unique');
    }

    private function hasStocksOldUnique(): bool
    {
        return $this->hasIndexByName('stocks', 'stocks_company_variant_warehouse_unique');
    }

    private function hasIndexByName(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            $rows = $connection->select('SELECT 1 FROM sqlite_master WHERE type = ? AND name = ? LIMIT 1', ['index', $name]);

            return ! empty($rows);
        }

        $db = $connection->getDatabaseName();
        $r = $connection->select(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$db, $table, $name]
        );

        return isset($r[0]) && (int) $r[0]->c > 0;
    }

    private function tryDropBinForeign(string $table): void
    {
        try {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['bin_id']);
            });
        } catch (\Throwable) {
        }
    }

    private function tryAddBinForeign(string $table): void
    {
        try {
            Schema::table($table, function (Blueprint $t) {
                $t->foreign('bin_id')->references('id')->on('bins')->restrictOnDelete();
            });
        } catch (\Throwable) {
        }
    }

    private function tryDropStocksOldUnique(): void
    {
        if (! $this->hasStocksOldUnique()) {
            return;
        }
        try {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropUnique('stocks_company_variant_warehouse_unique');
            });
        } catch (\Throwable) {
        }
    }

    private function tryDropStocksNewUnique(): void
    {
        if (! $this->hasStocksNewUnique()) {
            return;
        }
        try {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropUnique('stocks_company_variant_warehouse_bin_unique');
            });
        } catch (\Throwable) {
        }
    }

    private function tryDropIndex(string $table, string $name): void
    {
        if (! $this->hasIndexByName($table, $name)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $table) use ($name) {
                $table->dropIndex($name);
            });
        } catch (\Throwable) {
        }
    }

    private function ensureDefaultBinsExist(): void
    {
        $code = \App\Models\Bin::DEFAULT_CODE;

        $warehouses = DB::table('warehouses')->select('id', 'company_id')->get();

        foreach ($warehouses as $w) {
            $exists = DB::table('bins')
                ->where('warehouse_id', $w->id)
                ->where('code', $code)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('bins')->insert([
                'company_id' => $w->company_id,
                'warehouse_id' => $w->id,
                'name' => 'Default',
                'code' => $code,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function backfillStockLayersBinId(): void
    {
        $code = \App\Models\Bin::DEFAULT_CODE;

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('
                UPDATE stock_layers AS sl
                INNER JOIN bins AS b
                    ON b.company_id = sl.company_id
                    AND b.warehouse_id = sl.warehouse_id
                    AND b.code = ?
                SET sl.bin_id = b.id
            ', [$code]);

            return;
        }

        DB::table('stock_layers')
            ->whereNull('bin_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($code) {
                foreach ($rows as $sl) {
                    $bid = DB::table('bins')
                        ->where('company_id', $sl->company_id)
                        ->where('warehouse_id', $sl->warehouse_id)
                        ->where('code', $code)
                        ->value('id');
                    if ($bid) {
                        DB::table('stock_layers')->where('id', $sl->id)->update(['bin_id' => $bid]);
                    }
                }
            });
    }

    private function backfillStocksBinId(): void
    {
        $code = \App\Models\Bin::DEFAULT_CODE;

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('
                UPDATE stocks AS s
                INNER JOIN bins AS b
                    ON b.company_id = s.company_id
                    AND b.warehouse_id = s.warehouse_id
                    AND b.code = ?
                SET s.bin_id = b.id
            ', [$code]);

            return;
        }

        DB::table('stocks')
            ->whereNull('bin_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($code) {
                foreach ($rows as $s) {
                    $bid = DB::table('bins')
                        ->where('company_id', $s->company_id)
                        ->where('warehouse_id', $s->warehouse_id)
                        ->where('code', $code)
                        ->value('id');
                    if ($bid) {
                        DB::table('stocks')->where('id', $s->id)->update(['bin_id' => $bid]);
                    }
                }
            });
    }

    private function backfillStockMovementsBinId(): void
    {
        $code = \App\Models\Bin::DEFAULT_CODE;

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('
                UPDATE stock_movements AS sm
                INNER JOIN bins AS b
                    ON b.company_id = sm.company_id
                    AND b.warehouse_id = sm.warehouse_id
                    AND b.code = ?
                SET sm.bin_id = b.id
            ', [$code]);

            return;
        }

        DB::table('stock_movements')
            ->whereNull('bin_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($code) {
                foreach ($rows as $sm) {
                    $bid = DB::table('bins')
                        ->where('company_id', $sm->company_id)
                        ->where('warehouse_id', $sm->warehouse_id)
                        ->where('code', $code)
                        ->value('id');
                    if ($bid) {
                        DB::table('stock_movements')->where('id', $sm->id)->update(['bin_id' => $bid]);
                    }
                }
            });
    }
};
