<?php

use App\Models\Bill;
use App\Models\Party;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\DebitNote;
use App\Models\CreditNote;
use App\Services\Accounting\GlPostingRegistry;

it('recognises every document type that has a posting service', function (object $document) {
    expect(app(GlPostingRegistry::class)->supports($document))->toBeTrue();
})->with([
    'invoice' => fn () => new Invoice,
    'credit note' => fn () => new CreditNote,
    'debit note' => fn () => new DebitNote,
    'bill' => fn () => new Bill,
    'expense' => fn () => new Expense,
]);

it('does not support an unrelated model', function () {
    expect(app(GlPostingRegistry::class)->supports(new Party))->toBeFalse();
});

it('throws for an unsupported document on post/isPosted', function () {
    $registry = app(GlPostingRegistry::class);

    expect(fn () => $registry->isPosted(new Party))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registry->post(new Party))->toThrow(InvalidArgumentException::class);
});
