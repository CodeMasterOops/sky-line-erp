<?php

namespace App\Services\Nepal;

/**
 * Converts numbers to Nepali words and formats numbers in lakh/crore system.
 */
class NepaliNumberService
{
    private const ONES = [
        '', 'एक', 'दुई', 'तीन', 'चार', 'पाँच', 'छ', 'सात', 'आठ', 'नौ',
        'दश', 'एघार', 'बाह्र', 'तेह्र', 'चौध', 'पन्ध्र', 'सोह्र', 'सत्र',
        'अठार', 'उन्नाइस', 'बीस', 'एक्काइस', 'बाइस', 'तेइस', 'चौबीस',
        'पच्चीस', 'छब्बीस', 'सत्ताइस', 'अट्ठाइस', 'उनन्तिस', 'तीस',
        'एकतीस', 'बत्तीस', 'तेत्तीस', 'चौंतीस', 'पैंतीस', 'छत्तीस',
        'सैंतीस', 'अठतीस', 'उनन्चालीस', 'चालीस', 'एकचालीस', 'बयालीस',
        'त्रिचालीस', 'चौवालीस', 'पैंतालीस', 'छयालीस', 'सत्चालीस',
        'अड्तालीस', 'उनन्पचास', 'पचास', 'एकाउन्न', 'बाउन्न', 'त्रिपन्न',
        'चौवन्न', 'पचपन्न', 'छपन्न', 'सन्तावन्न', 'अट्ठावन्न', 'उनान्ठ',
        'साठी', 'एकसट्ठी', 'बसट्ठी', 'त्रिसट्ठी', 'चौसट्ठी', 'पैंसट्ठी',
        'छैसट्ठी', 'सड्सट्ठी', 'अड्सट्ठी', 'उनन्सत्तरी', 'सत्तरी',
        'एकहत्तर', 'बहत्तर', 'त्रिहत्तर', 'चौहत्तर', 'पचहत्तर', 'छहत्तर',
        'सतहत्तर', 'अठहत्तर', 'उनासी', 'असी', 'एकासी', 'बयासी', 'त्रियासी',
        'चौरासी', 'पचासी', 'छयासी', 'सतासी', 'अठासी', 'उनान्नब्बे', 'नब्बे',
        'एकान्नब्बे', 'बयान्नब्बे', 'त्रियान्नब्बे', 'चौरान्नब्बे',
        'पन्चान्नब्बे', 'छयान्नब्बे', 'सन्तान्नब्बे', 'अन्ठान्नब्बे',
        'उनान्सय', 'एक सय',
    ];

    private const EN_ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const EN_TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    /**
     * Convert a float amount to English words in Lakh/Crore format (Nepal style).
     * Example: 1200.00 → "One Thousand Two Hundred Rupees Only"
     */
    public function amountToWordsEn(float $amount): string
    {
        $amount = round($amount, 2);
        $intPart = (int) floor($amount);
        $decPart = (int) round(($amount - $intPart) * 100);

        $words = $this->numberToWordsEn($intPart).' Rupees';
        if ($decPart > 0) {
            $words .= ' and '.$this->numberToWordsEn($decPart).' Paisa';
        }

        return $words.' Only';
    }

    public function numberToWordsEn(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }
        if ($n < 0) {
            return 'Minus '.$this->numberToWordsEn(abs($n));
        }

        $parts = [];

        if ($n >= 1_00_00_00_000) {
            $parts[] = $this->numberToWordsEn((int) ($n / 1_00_00_00_000)).' Arab';
            $n %= 1_00_00_00_000;
        }
        if ($n >= 1_00_00_000) {
            $parts[] = $this->numberToWordsEn((int) ($n / 1_00_00_000)).' Crore';
            $n %= 1_00_00_000;
        }
        if ($n >= 1_00_000) {
            $parts[] = $this->numberToWordsEn((int) ($n / 1_00_000)).' Lakh';
            $n %= 1_00_000;
        }
        if ($n >= 1_000) {
            $parts[] = $this->numberToWordsEn((int) ($n / 1_000)).' Thousand';
            $n %= 1_000;
        }
        if ($n >= 100) {
            $parts[] = self::EN_ONES[(int) ($n / 100)].' Hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $word = self::EN_TENS[(int) ($n / 10)];
            if ($n % 10) {
                $word .= '-'.self::EN_ONES[$n % 10];
            }
            $parts[] = $word;
        } elseif ($n > 0) {
            $parts[] = self::EN_ONES[$n];
        }

        return implode(' ', $parts);
    }

    /**
     * Convert a float amount to Nepali words (for invoice bottom totals).
     * Example: 13650.50 → "तेह्र हजार छ सय पचास रुपैयाँ पचास पैसा"
     */
    public function amountToWords(float $amount): string
    {
        $amount = round($amount, 2);
        $intPart = (int) floor($amount);
        $decPart = (int) round(($amount - $intPart) * 100);

        $words = $this->numberToWords($intPart).' रुपैयाँ';

        if ($decPart > 0) {
            $words .= ' '.$this->numberToWords($decPart).' पैसा';
        }

        return $words.' मात्र';
    }

    /**
     * Convert an integer to Nepali words.
     */
    public function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'शून्य';
        }

        if ($number < 0) {
            return 'माइनस '.$this->numberToWords(abs($number));
        }

        if ($number <= 100) {
            return self::ONES[$number];
        }

        $parts = [];

        $arab = (int) floor($number / 1_00_00_00_000);
        if ($arab > 0) {
            $parts[] = $this->numberToWords($arab).' अरब';
            $number %= 1_00_00_00_000;
        }

        $crore = (int) floor($number / 1_00_00_000);
        if ($crore > 0) {
            $parts[] = $this->numberToWords($crore).' करोड';
            $number %= 1_00_00_000;
        }

        $lakh = (int) floor($number / 1_00_000);
        if ($lakh > 0) {
            $parts[] = $this->numberToWords($lakh).' लाख';
            $number %= 1_00_000;
        }

        $hazar = (int) floor($number / 1_000);
        if ($hazar > 0) {
            $parts[] = $this->numberToWords($hazar).' हजार';
            $number %= 1_000;
        }

        $say = (int) floor($number / 100);
        if ($say > 0) {
            $parts[] = self::ONES[$say].' सय';
            $number %= 100;
        }

        if ($number > 0) {
            $parts[] = $number <= 100
                ? self::ONES[$number]
                : $this->numberToWords($number);
        }

        return implode(' ', $parts);
    }

    /**
     * Format a number in the Nepali lakh/crore system with commas.
     * Example: 1500000 → "15,00,000"
     */
    public function formatLakhCrore(float $number, int $decimals = 2): string
    {
        $formatted = number_format($number, $decimals);
        [$intStr, $decStr] = explode('.', $formatted) + ['', ''];

        // Apply Nepali grouping: last 3 digits, then groups of 2
        $len = strlen($intStr);
        if ($len <= 3) {
            return $intStr.($decStr !== '' ? '.'.$decStr : '');
        }

        $last3 = substr($intStr, -3);
        $rest = substr($intStr, 0, $len - 3);
        $groups = [];

        while (strlen($rest) > 2) {
            $groups[] = substr($rest, -2);
            $rest = substr($rest, 0, -2);
        }

        if ($rest !== '') {
            $groups[] = $rest;
        }

        $groups = array_reverse($groups);
        $result = implode(',', $groups).','.$last3;

        return $result.($decStr !== '' ? '.'.$decStr : '');
    }
}
