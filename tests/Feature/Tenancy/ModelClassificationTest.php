<?php

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Database\Eloquent\Model;

/*
| Phase 0 manifest-consistency test
| (docs/branch-isolation-complete-implementation-plan.md).
|
| Guards config/tenancy.php so that:
|   1. Every Eloquent model under app/Models is classified in exactly one
|      exclusive bucket (no model is forgotten, none classified twice).
|   2. The manifest only references classes that actually exist.
|   3. Branch-owned models that are NOT pending migration use BranchTenant.
|   4. Company-global models use MultiTenant (with documented exceptions).
|
| When a new model is added, bucket (1) fails until it is classified — forcing
| a tier decision for every future module. Items in `pending_branch_migration`
| are exempt from (3) until their phase ships, then are removed from the list.
*/

/**
 * @return array<int, class-string<Model>>
 */
function allModelClasses(): array
{
    $classes = [];

    foreach (glob(app_path('Models').'/*.php') as $file) {
        $class = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);

        if (! class_exists($class)) {
            continue;
        }

        if (! is_subclass_of($class, Model::class)) {
            continue;
        }

        $classes[] = $class;
    }

    sort($classes);

    return $classes;
}

/**
 * The five mutually-exclusive classification buckets, flattened to a class list.
 *
 * @return array<int, class-string<Model>>
 */
function classifiedModelClasses(): array
{
    return array_merge(
        config('tenancy.branch_owned'),
        array_keys(config('tenancy.branch_owned_via_parent')),
        config('tenancy.company_global'),
        array_keys(config('tenancy.company_global_via_parent')),
        config('tenancy.system_global'),
        array_keys(config('tenancy.unscoped_special')),
    );
}

it('classifies every model into exactly one tenancy bucket', function () {
    $models = allModelClasses();
    $classified = classifiedModelClasses();

    $unclassified = array_values(array_diff($models, $classified));
    expect($unclassified)->toBe(
        [],
        'These models are not classified in config/tenancy.php: '.implode(', ', $unclassified)
    );
});

it('does not reference unknown or duplicate classes in the manifest', function () {
    $models = allModelClasses();
    $classified = classifiedModelClasses();

    $unknown = array_values(array_diff($classified, $models));
    expect($unknown)->toBe(
        [],
        'These manifest entries are not real models: '.implode(', ', $unknown)
    );

    $duplicates = array_values(array_unique(
        array_diff_assoc($classified, array_unique($classified))
    ));
    expect($duplicates)->toBe(
        [],
        'These models appear in more than one bucket: '.implode(', ', $duplicates)
    );
});

it('applies BranchTenant to every fully-migrated branch-owned model', function () {
    $pending = config('tenancy.pending_branch_migration');

    $missing = collect(config('tenancy.branch_owned'))
        ->reject(fn (string $model) => in_array($model, $pending, true))
        ->reject(fn (string $model) => in_array(BranchTenant::class, class_uses_recursive($model), true))
        ->values()
        ->all();

    expect($missing)->toBe(
        [],
        'Branch-owned models missing the BranchTenant trait (add it or list under pending_branch_migration): '.implode(', ', $missing)
    );
});

it('applies MultiTenant to every company-global model', function () {
    // Models flagged for review in the manifest: either missing a company_id
    // column, or carrying one without the MultiTenant scope yet (Phase 0 finding;
    // do not add the trait until the company-scope review in a later phase).
    $exceptions = [
        \App\Models\Discount::class,
        \App\Models\FiscalYear::class,
        \App\Models\ImportValueAlias::class,
        \App\Models\Subscription::class,
        \App\Models\TaxTemplate::class,
        \App\Models\TdsCertificateSequence::class,
    ];

    $missing = collect(config('tenancy.company_global'))
        ->reject(fn (string $model) => in_array($model, $exceptions, true))
        ->reject(fn (string $model) => in_array(MultiTenant::class, class_uses_recursive($model), true))
        ->values()
        ->all();

    expect($missing)->toBe(
        [],
        'Company-global models missing the MultiTenant trait: '.implode(', ', $missing)
    );
});

it('keeps pending_branch_migration entries within the branch_owned tier', function () {
    $branchOwned = config('tenancy.branch_owned');

    $stray = array_values(array_diff(config('tenancy.pending_branch_migration'), $branchOwned));

    expect($stray)->toBe(
        [],
        'These pending_branch_migration entries are not in branch_owned: '.implode(', ', $stray)
    );
});

it('has no models still pending branch migration (Phase 6 enforcement)', function () {
    // The branch-isolation migration is complete: every branch-owned model
    // carries branch_id + BranchTenant and is enforced by the trait test above.
    // A new Tier A model added here must ship its migration in the same change,
    // not be parked. If you must temporarily park one, do so deliberately —
    // this guard exists so the backlog cannot silently grow.
    expect(config('tenancy.pending_branch_migration'))->toBe(
        [],
        'Models are parked pending branch migration: '.implode(', ', config('tenancy.pending_branch_migration'))
    );
});
