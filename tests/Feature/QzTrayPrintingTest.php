<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use App\Services\Pos\QzTrayService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function warmQzTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

/**
 * Generate a throwaway RSA key pair + self-signed certificate and point the
 * qz config at the resulting files.
 *
 * @return array{cert: string, public: string}
 */
function configureQzSigning(): array
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($key, $privatePem);
    $details = openssl_pkey_get_details($key);
    $publicPem = $details['key'];

    $csr = openssl_csr_new(['commonName' => 'qz-test'], $key);
    $x509 = openssl_csr_sign($csr, null, $key, 365);
    openssl_x509_export($x509, $certPem);

    $keyPath = tempnam(sys_get_temp_dir(), 'qzkey');
    $certPath = tempnam(sys_get_temp_dir(), 'qzcert');
    file_put_contents($keyPath, $privatePem);
    file_put_contents($certPath, $certPem);

    config([
        'qz.private_key_path' => $keyPath,
        'qz.certificate_path' => $certPath,
        'qz.private_key_passphrase' => null,
        'qz.algorithm' => 'SHA512',
    ]);

    return ['cert' => trim($certPem), 'public' => $publicPem];
}

beforeEach(function () {
    Cache::flush();
    warmQzTablesCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Test Co',
        'code' => 'TC',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'POS Tester',
        'email' => 'qz-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    config(['qz.certificate_path' => null, 'qz.private_key_path' => null]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('reports unconfigured when no certificate is set', function () {
    $response = $this->getJson('/api/admin/pos/qz/certificate');

    $response->assertSuccessful();
    expect($response->json('configured'))->toBeFalse();
    expect($response->json('certificate'))->toBeNull();
});

it('returns the certificate when signing is configured', function () {
    $keys = configureQzSigning();

    $response = $this->getJson('/api/admin/pos/qz/certificate');

    $response->assertSuccessful();
    expect($response->json('configured'))->toBeTrue();
    expect($response->json('certificate'))->toBe($keys['cert']);
});

it('rejects signing when not configured', function () {
    $response = $this->postJson('/api/admin/pos/qz/sign', ['request' => 'hello-qz']);

    $response->assertStatus(422);
});

it('requires a request payload to sign', function () {
    configureQzSigning();

    $response = $this->postJson('/api/admin/pos/qz/sign', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrorFor('request');
});

it('signs a payload that verifies against the public key', function () {
    $keys = configureQzSigning();
    $payload = 'qz-tray-request-'.uniqid();

    $response = $this->postJson('/api/admin/pos/qz/sign', ['request' => $payload]);

    $response->assertSuccessful();
    $signature = base64_decode($response->json('signature'));

    $verified = openssl_verify($payload, $signature, $keys['public'], OPENSSL_ALGO_SHA512);
    expect($verified)->toBe(1);
});

it('signs directly through the service', function () {
    $keys = configureQzSigning();

    $signature = base64_decode(app(QzTrayService::class)->sign('direct-payload'));

    expect(openssl_verify('direct-payload', $signature, $keys['public'], OPENSSL_ALGO_SHA512))->toBe(1);
});
