<?php

use App\Enums\AccountGroupTypeEnum;

it('infers account type from a known root code', function (string $code, AccountGroupTypeEnum $expected) {
    expect(AccountGroupTypeEnum::infer($code, 'Anything'))->toBe($expected);
})->with([
    ['ASS', AccountGroupTypeEnum::Asset],
    ['LIA', AccountGroupTypeEnum::Liability],
    ['EQU', AccountGroupTypeEnum::Equity],
    ['INC', AccountGroupTypeEnum::Income],
    ['EXP', AccountGroupTypeEnum::Expense],
]);

it('falls back to the name when the code is unknown', function (string $name, AccountGroupTypeEnum $expected) {
    expect(AccountGroupTypeEnum::infer('ZZZ', $name))->toBe($expected);
})->with([
    ['Assets', AccountGroupTypeEnum::Asset],
    ['Liabilities', AccountGroupTypeEnum::Liability],
    ['Equity', AccountGroupTypeEnum::Equity],
    ['Revenue', AccountGroupTypeEnum::Income],
    ['Expenditure', AccountGroupTypeEnum::Expense],
]);

it('returns null when neither code nor name can be classified', function () {
    expect(AccountGroupTypeEnum::infer('CUSTOM', 'My Custom Group'))->toBeNull()
        ->and(AccountGroupTypeEnum::infer(null, null))->toBeNull();
});
