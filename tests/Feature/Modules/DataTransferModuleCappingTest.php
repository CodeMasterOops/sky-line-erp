<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use Illuminate\Http\UploadedFile;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;

/*
| Phase 9 — import / export capping
| (docs/module-capping-and-advanced-handling-plan.md gaps E1/E2).
|
| Having the Data Import / Export module is not entitlement to every entity in
| the wizard: importing products into an Inventory module the company does not
| run would create rows it cannot see, in a module that is supposed to be off.
*/

function actAsTransferUser(array $modules): App\Models\Company
{
    $company = makeCompany('Acme '.uniqid(), strtoupper(substr(md5(uniqid()), 0, 5)));
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules($modules)->create()->id,
    ]);
    $company->refresh();

    $user = User::create([
        'company_id' => $company->id,
        'name' => 'Owner',
        'email' => 'owner+'.uniqid().'@acme.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($user, [], 'admin');

    return $company;
}

it('maps every transfer entity to a module the registry knows', function () {
    $registry = app(App\Services\Modules\ModuleRegistry::class);

    foreach (DataTransferEntityTypeEnum::cases() as $case) {
        $moduleKey = $case->module();

        expect($moduleKey === null || $registry->has($moduleKey))
            ->toBeTrue("Entity [{$case->value}] names unknown module [{$moduleKey}].");
    }
});

it('agrees with the module registry about who owns each entity', function () {
    // config/modules.php repeats the ownership as `data_transfer_entities`;
    // the two drifting apart is how E1 got in.
    $registry = app(App\Services\Modules\ModuleRegistry::class);

    foreach ($registry->all() as $key => $module) {
        foreach ($module['data_transfer_entities'] as $entity) {
            $case = DataTransferEntityTypeEnum::tryFrom($entity);

            expect($case)->not->toBeNull("Module [{$key}] declares unknown entity [{$entity}].");

            // `data-transfer` owns the core entity, whose own module() is null.
            $expected = $key === 'data-transfer' ? null : $key;

            expect($case->module())->toBe($expected, "Entity [{$entity}] ownership disagrees with module [{$key}].");
        }
    }
});

it('offers only the entities the company can actually transfer', function () {
    actAsTransferUser(['accounting', 'inventory', 'sales', 'data-transfer']);

    $values = array_column($this->getJson('/api/admin/data-transfers/entities')->assertSuccessful()->json('data'), 'value');

    expect($values)
        ->toContain('party', 'product', 'warehouse', 'invoice', 'account')
        ->not->toContain('bill', 'purchase_order');
});

it('refuses an export of an entity whose module is off', function () {
    actAsTransferUser(['accounting', 'inventory', 'sales', 'data-transfer']);

    $this->postJson('/api/admin/data-transfers/exports', [
        'entity_type' => 'bill',
        'format' => 'csv',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'module_disabled')
        ->assertJsonPath('module', 'purchase');
});

it('allows an export of an entity whose module is on', function () {
    actAsTransferUser(['accounting', 'inventory', 'sales', 'purchase', 'data-transfer']);

    $this->postJson('/api/admin/data-transfers/exports', [
        'entity_type' => 'bill',
        'format' => 'csv',
    ])->assertSuccessful();
});

it('refuses a product import when inventory is off', function () {
    actAsTransferUser(['accounting', 'data-transfer']);

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => UploadedFile::fake()->createWithContent('products.csv', "code,name\nP1,Widget\n"),
        'entity_type' => 'product',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'module_disabled')
        ->assertJsonPath('module', 'inventory');
});

it('still allows the core party import when only data transfer is on', function () {
    actAsTransferUser(['accounting', 'data-transfer']);

    $this->postJson('/api/admin/data-transfers/imports', [
        'file' => UploadedFile::fake()->createWithContent('parties.csv', "name,type\nAcme,customer\n"),
        'entity_type' => 'party',
    ])->assertSuccessful();
});
