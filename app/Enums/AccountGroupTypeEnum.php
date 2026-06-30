<?php

namespace App\Enums;

enum AccountGroupTypeEnum: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    /**
     * Best-effort classification for a root account group that has no explicit
     * account_type set, using its code first and then its name. Returns null when
     * the group cannot be confidently classified.
     */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::Equity, self::Income => 'credit',
        };
    }

    public static function infer(?string $code, ?string $name): ?self
    {
        $byCode = [
            'ASS' => self::Asset,
            'LIA' => self::Liability,
            'EQU' => self::Equity,
            'INC' => self::Income,
            'EXP' => self::Expense,
        ];

        if ($code !== null && isset($byCode[$code])) {
            return $byCode[$code];
        }

        return match (strtolower(trim((string) $name))) {
            'assets', 'asset' => self::Asset,
            'liabilities', 'liability' => self::Liability,
            'equity', 'equities' => self::Equity,
            'income', 'incomes', 'revenue', 'revenues' => self::Income,
            'expense', 'expenses', 'expenditure', 'expenditures', 'costs' => self::Expense,
            default => null,
        };
    }
}
