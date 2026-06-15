const EN_ONES = [
    '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
    'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
    'Seventeen', 'Eighteen', 'Nineteen',
];

const EN_TENS = [
    '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
];

export function numberToWordsEn(n) {
    if (n === 0) {
        return 'Zero';
    }

    if (n < 0) {
        return `Minus ${numberToWordsEn(Math.abs(n))}`;
    }

    const parts = [];

    if (n >= 1_00_00_00_000) {
        parts.push(`${numberToWordsEn(Math.floor(n / 1_00_00_00_000))} Arab`);
        n %= 1_00_00_00_000;
    }
    if (n >= 1_00_00_000) {
        parts.push(`${numberToWordsEn(Math.floor(n / 1_00_00_000))} Crore`);
        n %= 1_00_00_000;
    }
    if (n >= 1_00_000) {
        parts.push(`${numberToWordsEn(Math.floor(n / 1_00_000))} Lakh`);
        n %= 1_00_000;
    }
    if (n >= 1_000) {
        parts.push(`${numberToWordsEn(Math.floor(n / 1_000))} Thousand`);
        n %= 1_000;
    }
    if (n >= 100) {
        parts.push(`${EN_ONES[Math.floor(n / 100)]} Hundred`);
        n %= 100;
    }
    if (n >= 20) {
        let word = EN_TENS[Math.floor(n / 10)];
        if (n % 10) {
            word += `-${EN_ONES[n % 10]}`;
        }
        parts.push(word);
    } else if (n > 0) {
        parts.push(EN_ONES[n]);
    }

    return parts.join(' ');
}

export function amountToWordsEn(amount) {
    const rounded = Math.round(Number(amount || 0) * 100) / 100;
    const intPart = Math.floor(rounded);
    const decPart = Math.round((rounded - intPart) * 100);

    let words = `${numberToWordsEn(intPart)} Rupees`;
    if (decPart > 0) {
        words += ` and ${numberToWordsEn(decPart)} Paisa`;
    }

    return `${words} Only`;
}
