<?php

use App\Services\TenantService;
use App\Jobs\Middleware\ResetsTenantContext;

afterEach(fn () => TenantService::reset());

it('clears the tenant context after a job completes', function () {
    TenantService::setCompanyId(7);
    TenantService::setBranchId(3);

    (new ResetsTenantContext)->handle(new stdClass, fn ($job) => 'done');

    expect(TenantService::companyId())->toBeNull()
        ->and(TenantService::branchId())->toBeNull();
});

it('clears the tenant context even when the job throws', function () {
    TenantService::setCompanyId(7);
    TenantService::setBranchId(3);

    try {
        (new ResetsTenantContext)->handle(new stdClass, fn ($job) => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
        // expected
    }

    expect(TenantService::companyId())->toBeNull()
        ->and(TenantService::branchId())->toBeNull();
});
