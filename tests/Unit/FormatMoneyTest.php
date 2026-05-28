<?php

it('formats positive amounts with the base currency symbol', function () {
    expect(format_money(1234.5))->toBe('Rs. 1,234.50');
});

it('returns an em dash for null and empty values', function () {
    expect(format_money(null))->toBe('—')
        ->and(format_money(''))->toBe('—');
});

it('respects a custom symbol override', function () {
    expect(format_money(100, '$'))->toBe('$ 100.00');
});

it('reads the default symbol from currency config', function () {
    config(['currency.symbol' => 'Rs.']);

    expect(format_money(0))->toBe('Rs. 0.00');
});
