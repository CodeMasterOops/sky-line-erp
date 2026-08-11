<?php

/**
 * Default document sequences provisioned for a new company.
 *
 * `module` names the module that issues the document, and the sequence is only
 * created for companies that run it — a service business has no use for a GRN
 * counter, and a company without Manufacturing has none for production orders.
 * A sequence with no `module` is core.
 *
 * DocumentSequencesStep replays the missing entries when a module is switched
 * on later, so nothing is lost by not creating them up front.
 */
return [

    ['document_type' => 'invoice', 'prefix' => 'INV-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'sales'],
    ['document_type' => 'sales_order', 'prefix' => 'SO-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'sales'],
    ['document_type' => 'quotation', 'prefix' => 'QT-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'sales'],
    ['document_type' => 'delivery_challan', 'prefix' => 'DC-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'sales'],
    ['document_type' => 'credit_note', 'prefix' => 'CN-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'sales'],
    ['document_type' => 'receipt', 'prefix' => 'REC-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'sales'],

    ['document_type' => 'debit_note', 'prefix' => 'DN-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'purchase'],
    ['document_type' => 'bill', 'prefix' => 'BILL-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'purchase'],
    ['document_type' => 'purchase_order', 'prefix' => 'PO-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'purchase'],
    ['document_type' => 'payment', 'prefix' => 'PAY-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'purchase'],
    ['document_type' => 'expense', 'prefix' => 'EXP-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'purchase'],

    ['document_type' => 'grn', 'prefix' => 'GRN-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'inventory'],
    ['document_type' => 'stock_adjustment', 'prefix' => 'SA-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'inventory'],
    ['document_type' => 'stock_transfer', 'prefix' => 'ST-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'inventory'],

    ['document_type' => 'payment_voucher', 'prefix' => 'PV-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'accounting'],
    ['document_type' => 'receipt_voucher', 'prefix' => 'RV-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'accounting'],
    ['document_type' => 'journal_voucher', 'prefix' => 'JV-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'accounting'],

    ['document_type' => 'production_order', 'prefix' => 'MO-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true, 'module' => 'manufacturing'],

];
