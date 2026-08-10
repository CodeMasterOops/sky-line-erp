<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\QueryException;
use App\Exceptions\ApiDatabaseExceptionRenderer;

function makeQueryException(string $message, string $sqlState = '23000'): QueryException
{
    $previous = new PDOException($message);
    $previous->errorInfo = [$sqlState, 1, $message];

    return new QueryException('sqlite', 'insert into "employees" ("employee_code") values (?)', ['EMP-1'], $previous);
}

it('maps unique violations to a friendly 422 message', function () {
    $renderer = new ApiDatabaseExceptionRenderer;
    $exception = makeQueryException(
        'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: employees.employee_code'
    );

    [$status, $message] = $renderer->mapQueryException($exception);

    expect($status)->toBe(422)
        ->and($message)->toBe('This value is already in use.')
        ->and($message)->not->toContain('SQLSTATE');
});

it('maps foreign key violations to a friendly 422 message', function () {
    $renderer = new ApiDatabaseExceptionRenderer;
    $exception = makeQueryException(
        'SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed'
    );

    [$status, $message] = $renderer->mapQueryException($exception);

    expect($status)->toBe(422)
        ->and($message)->toBe('A related record is missing or cannot be used.')
        ->and($message)->not->toContain('SQLSTATE');
});

it('maps other query exceptions to a generic 500 message', function () {
    $renderer = new ApiDatabaseExceptionRenderer;
    $exception = makeQueryException(
        'SQLSTATE[HY000]: General error: 1 no such table: missing',
        'HY000'
    );

    [$status, $message] = $renderer->mapQueryException($exception);

    expect($status)->toBe(500)
        ->and($message)->toBe('Unable to save. Please try again.')
        ->and($message)->not->toContain('SQLSTATE');
});

it('renders query exceptions as safe JSON for api requests', function () {
    Log::spy();

    $renderer = new ApiDatabaseExceptionRenderer;
    $request = Request::create('/api/admin/hr/employee', 'POST');
    $exception = makeQueryException(
        'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: employees.employee_code'
    );

    $response = $renderer->renderQueryException($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['message'])->toBe('This value is already in use.')
        ->and($response->getData(true)['message'])->not->toContain('SQLSTATE');
});

it('sanitizes sql-like throwable messages for api requests', function () {
    Log::spy();

    $renderer = new ApiDatabaseExceptionRenderer;
    $request = Request::create('/api/admin/hr/employee', 'POST');
    $exception = new RuntimeException('SQLSTATE[HY000]: General error: boom SQL: select 1');

    $response = $renderer->renderSqlLikeThrowable($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['message'])->toBe('Unable to save. Please try again.')
        ->and($response->getData(true)['message'])->not->toContain('SQLSTATE');
});

it('returns a safe json body when an api route throws a query exception', function () {
    Log::spy();

    Route::middleware('api')->post('/api/__test/query-exception', function () {
        throw makeQueryException(
            'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: employees.employee_code'
        );
    });

    $this->postJson('/api/__test/query-exception')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This value is already in use.');

    expect($this->postJson('/api/__test/query-exception')->json('message'))
        ->not->toContain('SQLSTATE');
});
