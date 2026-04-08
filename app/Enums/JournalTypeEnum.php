<?php

namespace App\Enums;

enum JournalTypeEnum: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case JOURNAL_VOUCHER = 'journal-voucher';
    case PAYMENT_VOUCHER = 'payment-voucher';
    case RECEIPT_VOUCHER = 'receipt-voucher';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::PURCHASE => 'Purchase',
            self::SALE => 'Sale',
            self::JOURNAL_VOUCHER => 'Journal Voucher',
            self::PAYMENT_VOUCHER => 'Payment Voucher',
            self::RECEIPT_VOUCHER => 'Receipt Voucher',
        };
    }
}
