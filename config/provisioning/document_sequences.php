<?php

/**
 * Default document sequences provisioned for every new company.
 * These configure the prefix and formatting for each document type.
 */
return [

    ['document_type' => 'invoice', 'prefix' => 'INV-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'sales_order', 'prefix' => 'SO-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'quotation', 'prefix' => 'QT-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'delivery_challan', 'prefix' => 'DC-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'credit_note', 'prefix' => 'CN-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'debit_note', 'prefix' => 'DN-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'bill', 'prefix' => 'BILL-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'purchase_order', 'prefix' => 'PO-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'grn', 'prefix' => 'GRN-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'payment', 'prefix' => 'PAY-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'receipt', 'prefix' => 'REC-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'payment_voucher', 'prefix' => 'PV-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'receipt_voucher', 'prefix' => 'RV-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'journal_voucher', 'prefix' => 'JV-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'expense', 'prefix' => 'EXP-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'stock_adjustment', 'prefix' => 'SA-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'stock_transfer', 'prefix' => 'ST-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],
    ['document_type' => 'production_order', 'prefix' => 'MO-', 'padding' => 5, 'separator' => '/', 'reset_yearly' => true],

];
