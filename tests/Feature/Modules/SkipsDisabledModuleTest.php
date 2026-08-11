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

it('wires the middleware into every job that does module-owned work', function (string $jobClass, string $moduleKey) {
    // The middleware only helps if the job actually declares it — this is the
    // wiring, asserted cheaply so a new module-owned job cannot forget it.
    $reflection = new ReflectionClass($jobClass);

    expect($reflection->hasMethod('middleware'))->toBeTrue("[{$jobClass}] declares no middleware().")
        ->and(file_get_contents($reflection->getFileName()))
        ->toContain("new SkipsDisabledModule('{$moduleKey}'");
})->with([
    'IRD sync' => [App\Jobs\SyncInvoiceToIrdJob::class, 'nepal-compliance'],
    'low stock alert' => [App\Jobs\CheckLowStockJob::class, 'inventory'],
    'data transfer notification' => [App\Jobs\DataTransfer\NotifyDataTransferCompleteJob::class, 'data-transfer'],
]);
