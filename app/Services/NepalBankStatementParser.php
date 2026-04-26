<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Parse bank statement CSVs exported from Nepal commercial banks.
 * Supports: NMB Bank, Nabil Bank, Himalayan Bank, Global IME.
 */
class NepalBankStatementParser
{
    /**
     * Detect bank and parse the CSV file contents.
     *
     * @param  string  $csvContent  Raw CSV string
     * @param  string  $bank        Hint: 'nmb'|'nabil'|'himalayan'|'global_ime'|'auto'
     * @return Collection<array{date, description, reference, debit, credit, balance}>
     */
    public function parse(string $csvContent, string $bank = 'auto'): Collection
    {
        $lines  = array_filter(explode("\n", str_replace("\r\n", "\n", $csvContent)));
        $rows   = array_map('str_getcsv', $lines);

        if ($bank === 'auto') {
            $bank = $this->detect($rows);
        }

        return match ($bank) {
            'nmb'       => $this->parseNmb($rows),
            'nabil'     => $this->parseNabil($rows),
            'himalayan' => $this->parseHimalayan($rows),
            'global_ime'=> $this->parseGlobalIme($rows),
            default     => $this->parseGeneric($rows),
        };
    }

    private function detect(array $rows): string
    {
        $header = implode(',', $rows[0] ?? []);
        if (str_contains($header, 'Txn Date') && str_contains($header, 'Narration')) {
            return 'nmb';
        }
        if (str_contains($header, 'Transaction Date') && str_contains($header, 'Particulars')) {
            return 'nabil';
        }
        if (str_contains($header, 'Value Date') && str_contains($header, 'Description')) {
            return 'himalayan';
        }
        return 'generic';
    }

    /** NMB Bank format: Txn Date | Narration | Cheque No | Debit | Credit | Balance */
    private function parseNmb(array $rows): Collection
    {
        $results = collect();
        $started = false;
        foreach ($rows as $row) {
            if (!$started) {
                if (isset($row[0]) && str_contains($row[0], 'Txn Date')) {
                    $started = true;
                }
                continue;
            }
            if (empty(array_filter($row))) continue;

            $date   = $this->parseDate(trim($row[0] ?? ''));
            $desc   = trim($row[1] ?? '');
            $ref    = trim($row[2] ?? '');
            $debit  = $this->toFloat($row[3] ?? 0);
            $credit = $this->toFloat($row[4] ?? 0);
            $bal    = $this->toFloat($row[5] ?? 0);

            if ($date) {
                $results->push(compact('date', 'description', 'ref', 'debit', 'credit', 'bal') + ['description' => $desc, 'reference' => $ref, 'balance' => $bal]);
            }
        }
        return $results;
    }

    /** Nabil Bank: Transaction Date | Particulars | Ref/Cheque | Debit | Credit | Balance */
    private function parseNabil(array $rows): Collection
    {
        $results = collect();
        $started = false;
        foreach ($rows as $row) {
            if (!$started) {
                if (isset($row[0]) && str_contains($row[0], 'Transaction Date')) {
                    $started = true;
                }
                continue;
            }
            if (empty(array_filter($row))) continue;

            $date   = $this->parseDate(trim($row[0] ?? ''));
            $desc   = trim($row[1] ?? '');
            $ref    = trim($row[2] ?? '');
            $debit  = $this->toFloat($row[3] ?? 0);
            $credit = $this->toFloat($row[4] ?? 0);
            $bal    = $this->toFloat($row[5] ?? 0);

            if ($date) {
                $results->push(['date' => $date, 'description' => $desc, 'reference' => $ref, 'debit' => $debit, 'credit' => $credit, 'balance' => $bal]);
            }
        }
        return $results;
    }

    /** Himalayan Bank: Value Date | Description | Reference | Debit | Credit | Balance */
    private function parseHimalayan(array $rows): Collection
    {
        $results = collect();
        $started = false;
        foreach ($rows as $row) {
            if (!$started) {
                if (isset($row[0]) && str_contains($row[0], 'Value Date')) {
                    $started = true;
                }
                continue;
            }
            if (empty(array_filter($row))) continue;

            $date   = $this->parseDate(trim($row[0] ?? ''));
            $desc   = trim($row[1] ?? '');
            $ref    = trim($row[2] ?? '');
            $debit  = $this->toFloat($row[3] ?? 0);
            $credit = $this->toFloat($row[4] ?? 0);
            $bal    = $this->toFloat($row[5] ?? 0);

            if ($date) {
                $results->push(['date' => $date, 'description' => $desc, 'reference' => $ref, 'debit' => $debit, 'credit' => $credit, 'balance' => $bal]);
            }
        }
        return $results;
    }

    /** Global IME Bank: same column layout as Himalayan */
    private function parseGlobalIme(array $rows): Collection
    {
        return $this->parseHimalayan($rows);
    }

    /** Generic fallback: assume Date|Description|Reference|Debit|Credit|Balance */
    private function parseGeneric(array $rows): Collection
    {
        $results = collect();
        $skippedHeader = false;
        foreach ($rows as $row) {
            if (!$skippedHeader) { $skippedHeader = true; continue; }
            if (empty(array_filter($row))) continue;

            $date   = $this->parseDate(trim($row[0] ?? ''));
            $desc   = trim($row[1] ?? '');
            $ref    = trim($row[2] ?? '');
            $debit  = $this->toFloat($row[3] ?? 0);
            $credit = $this->toFloat($row[4] ?? 0);
            $bal    = $this->toFloat($row[5] ?? 0);

            if ($date) {
                $results->push(['date' => $date, 'description' => $desc, 'reference' => $ref, 'debit' => $debit, 'credit' => $credit, 'balance' => $bal]);
            }
        }
        return $results;
    }

    private function parseDate(string $value): ?string
    {
        if (empty($value)) return null;
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd M Y', 'd-M-Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)?->toDateString();
            } catch (\Throwable) {}
        }
        return null;
    }

    private function toFloat(mixed $value): float
    {
        return (float) str_replace([',', ' '], '', (string) $value);
    }
}
