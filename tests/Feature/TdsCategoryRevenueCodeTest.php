<?php

use App\Enums\TdsCategoryEnum;

it('maps service/contract/commission categories to revenue code 11112', function () {
    expect(TdsCategoryEnum::SERVICE_VAT_BILL->revenueCode())->toBe('11112');
    expect(TdsCategoryEnum::SERVICE_PAN_BILL->revenueCode())->toBe('11112');
    expect(TdsCategoryEnum::SERVICE_VAT_EXEMPT_INSTITUTION->revenueCode())->toBe('11112');
    expect(TdsCategoryEnum::CONTRACT_VAT_REGISTERED->revenueCode())->toBe('11112');
    expect(TdsCategoryEnum::COMMISSION->revenueCode())->toBe('11112');
});

it('maps rent categories to revenue code 11113', function () {
    expect(TdsCategoryEnum::RENT_PROPERTY->revenueCode())->toBe('11113');
    expect(TdsCategoryEnum::RENT_VEHICLE_VAT->revenueCode())->toBe('11113');
    expect(TdsCategoryEnum::RENT_VEHICLE_NO_VAT->revenueCode())->toBe('11113');
});

it('maps interest categories to revenue code 11212', function () {
    expect(TdsCategoryEnum::INTEREST_BANK_NATURAL_PERSON->revenueCode())->toBe('11212');
    expect(TdsCategoryEnum::INTEREST_COMPANY->revenueCode())->toBe('11212');
});

it('maps dividend to revenue code 11213', function () {
    expect(TdsCategoryEnum::DIVIDEND->revenueCode())->toBe('11213');
});

it('maps royalty to revenue code 11211', function () {
    expect(TdsCategoryEnum::ROYALTY->revenueCode())->toBe('11211');
});

it('maps windfall to revenue code 11214', function () {
    expect(TdsCategoryEnum::WINDFALL->revenueCode())->toBe('11214');
});

it('covers all enum cases with a revenue code', function () {
    foreach (TdsCategoryEnum::cases() as $case) {
        expect($case->revenueCode())->toBeString()->not->toBeEmpty();
    }
});
