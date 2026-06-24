<?php

/**
 * One-off code-mod: add the BranchTenant trait + 'branch_id' fillable to the
 * Phase 2b branch-owned models. Idempotent. Run once, then delete.
 */
$models = [
    'StockLayer', 'StockReservation', 'InventoryValuationSnapshot', 'SerialNumber', 'Batch',
    'ProductionOrder', 'CustomerAdvance', 'TdsDeduction', 'TdsReceivable', 'Cheque',
    'BankStatementImport', 'LandedCost', 'AdvanceApplication', 'PosCashMovement',
    'Attendance', 'LeaveApplication', 'PayrollRun', 'RecurringJournal', 'FixedAsset',
];

$base = dirname(__DIR__).'/app/Models/';

foreach ($models as $model) {
    $path = $base.$model.'.php';
    $src = file_get_contents($path);
    $orig = $src;

    // 1) import
    if (! str_contains($src, 'use App\\Traits\\BranchTenant;')) {
        if (str_contains($src, 'use App\\Traits\\MultiTenant;')) {
            $src = str_replace(
                'use App\\Traits\\MultiTenant;',
                "use App\\Traits\\MultiTenant;\nuse App\\Traits\\BranchTenant;",
                $src
            );
        } else {
            $src = preg_replace(
                '/(namespace App\\\\Models;\n)/',
                "$1\nuse App\\Traits\\BranchTenant;\n",
                $src,
                1
            );
        }
    }

    // 2) class-body trait use
    if (! preg_match('/\n\s+use BranchTenant\b/', $src)) {
        if (preg_match('/\n(\s+)use MultiTenant, SoftDeletes;/', $src, $m)) {
            $src = preg_replace('/\n(\s+)use MultiTenant, SoftDeletes;/', "\n$1use BranchTenant, MultiTenant, SoftDeletes;", $src, 1);
        } elseif (preg_match('/\n(\s+)use MultiTenant;/', $src, $m)) {
            $src = preg_replace('/\n(\s+)use MultiTenant;/', "\n$1use BranchTenant;\n$1use MultiTenant;", $src, 1);
        } else {
            // No MultiTenant in class body: insert right after the class opening brace.
            $src = preg_replace('/(class \w+ extends Model\n\{\n)/', "$1    use BranchTenant;\n", $src, 1);
        }
    }

    // 3) fillable
    if (! preg_match("/'branch_id'/", $src)) {
        $src = preg_replace("/(\n(\s+)'company_id',\n)/", "$1$2'branch_id',\n", $src, 1);
    }

    if ($src !== $orig) {
        file_put_contents($path, $src);
        echo "patched: {$model}\n";
    } else {
        echo "unchanged: {$model}\n";
    }
}
