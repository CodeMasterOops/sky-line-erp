<?php

use App\Models\Company;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\TdsCertificateSequence;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function seqWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    seqWarmCache();

    $fiscalYear = FiscalYear::create([
        'year_name' => '2081-82',
        'year_code' => '8182',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Seq Test Co',
        'code' => 'STC',
        'inventory_costing_method' => 'fifo',
    ]);
});

it('starts at sequence 1 for a new company and fiscal year', function () {
    $seq = DB::transaction(fn () => TdsCertificateSequence::nextFor($this->company->id, '8182'));

    expect($seq)->toBe(1);
});

it('increments the sequence on subsequent calls', function () {
    $first = DB::transaction(fn () => TdsCertificateSequence::nextFor($this->company->id, '8182'));
    $second = DB::transaction(fn () => TdsCertificateSequence::nextFor($this->company->id, '8182'));
    $third = DB::transaction(fn () => TdsCertificateSequence::nextFor($this->company->id, '8182'));

    expect($first)->toBe(1);
    expect($second)->toBe(2);
    expect($third)->toBe(3);
});

it('keeps separate sequences per fiscal year', function () {
    $seq8182 = DB::transaction(fn () => TdsCertificateSequence::nextFor($this->company->id, '8182'));
    $seq8283 = DB::transaction(fn () => TdsCertificateSequence::nextFor($this->company->id, '8283'));

    expect($seq8182)->toBe(1);
    expect($seq8283)->toBe(1);
});

it('stores the last_sequence value persistently', function () {
    DB::transaction(fn () => TdsCertificateSequence::nextFor($this->company->id, '8182'));
    DB::transaction(fn () => TdsCertificateSequence::nextFor($this->company->id, '8182'));

    $row = TdsCertificateSequence::where('company_id', $this->company->id)
        ->where('fiscal_year_code', '8182')
        ->first();

    expect($row->last_sequence)->toBe(2);
});
