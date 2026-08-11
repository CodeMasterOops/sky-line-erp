<?php

use App\Models\CompanyCategory;
use App\Jobs\Middleware\SkipsDisabledModule;

/*
| Phase 1 — the job-side capping primitive
| (docs/module-capping-and-advanced-handling-plan.md §4, D-4).
|
| A job can sit on the queue across a module change, so the check belongs at
| execution time. A disabled module is a legitimate state: the job is COMPLETED,
| never failed or retried.
*/

class SpyModuleJob
{
    public bool $ran = false;

    public function run(SkipsDisabledModule $middleware): void
    {
        $middleware->handle($this, function (): void {
            $this->ran = true;
        });
    }
}

beforeEach(function () {
    $this->company = makeCompany('Acme Fitness', 'ACME');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory'])->create()->id,
    ]);
    $this->company->refresh();
});

it('runs the job when the company still has the module', function () {
    $job = new SpyModuleJob;

    $job->run(new SkipsDisabledModule('inventory', (int) $this->company->id));

    expect($job->ran)->toBeTrue();
});

it('skips the job when the company no longer has the module', function () {
    $job = new SpyModuleJob;

    $job->run(new SkipsDisabledModule('crm', (int) $this->company->id));

    expect($job->ran)->toBeFalse();
});

it('runs the job when no company can be attributed', function () {
    // Fail open, exactly like EnsureModuleEnabled: a job we cannot attribute is
    // not a job we may silently drop.
    $job = new SpyModuleJob;

    $job->run(new SkipsDisabledModule('crm', null));

    expect($job->ran)->toBeTrue();
});

it('stops running as soon as the module is switched off', function () {
    $service = app(\App\Services\Modules\CompanyModuleService::class);

    $before = new SpyModuleJob;
    $before->run(new SkipsDisabledModule('inventory', (int) $this->company->id));

    $service->disable($this->company, 'inventory', cascade: true);

    $after = new SpyModuleJob;
    $after->run(new SkipsDisabledModule('inventory', (int) $this->company->id));

    expect($before->ran)->toBeTrue()
        ->and($after->ran)->toBeFalse();
});
