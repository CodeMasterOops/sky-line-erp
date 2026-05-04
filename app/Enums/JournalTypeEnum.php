<?php

namespace App\Enums;

enum JournalTypeEnum: string
{
    case PURCHASE_BILL = 'purchase-bill';
    case EXPENSE = 'expense';
    case INVOICE = 'invoice';
    case PAYMENT = 'payment';

    case RECEIPT = 'receipt';
    case JOURNAL_VOUCHER = 'journal-voucher';
    case PAYMENT_VOUCHER = 'payment-voucher';
    case RECEIPT_VOUCHER = 'receipt-voucher';
    case OPENING_BALANCE = 'opening-balance';
    case RECURRING = 'recurring';
    case DEPRECIATION = 'depreciation';
    case TDS_PAYABLE = 'tds-payable';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::PURCHASE_BILL => 'Purchase Bill',
            self::EXPENSE => 'Expense',
            self::INVOICE => 'Invoice',
            self::PAYMENT => 'Supplier Payment',
            self::RECEIPT => 'Customer Payment',
            self::JOURNAL_VOUCHER => 'Journal Voucher',
            self::PAYMENT_VOUCHER => 'Payment Voucher',
            self::RECEIPT_VOUCHER => 'Receipt Voucher',
            self::OPENING_BALANCE => 'Opening Balance',
            self::RECURRING => 'Recurring',
            self::DEPRECIATION => 'Depreciation',
            self::TDS_PAYABLE => 'TDS Payable',
        };
    }

    public static function typeList(): array
    {
        $list = [];

        foreach (self::cases() as $status) {
            $list[] = [
                'id' => $status->value,
                'name' => $status->label(),
            ];
        }

        return $list;
    }
}
