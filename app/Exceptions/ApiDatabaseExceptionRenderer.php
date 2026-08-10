<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ApiDatabaseExceptionRenderer
{
    public function renderQueryException(QueryException $e, Request $request): ?JsonResponse
    {
        if (! $this->isApiRequest($request)) {
            return null;
        }

        Log::error($e->getMessage(), [
            'exception' => $e::class,
            'sql' => $e->getSql(),
            'bindings' => $e->getBindings(),
        ]);

        [$status, $message] = $this->mapQueryException($e);

        return response()->json(['message' => $message], $status);
    }

    public function renderSqlLikeThrowable(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $this->isApiRequest($request)) {
            return null;
        }

        if ($e instanceof QueryException || $e instanceof HttpExceptionInterface) {
            return null;
        }

        if (! $this->looksLikeSqlMessage($e->getMessage())) {
            return null;
        }

        Log::error($e->getMessage(), [
            'exception' => $e::class,
        ]);

        return response()->json([
            'message' => 'Unable to save. Please try again.',
        ], 500);
    }

    /**
     * @return array{0: int, 1: string}
     */
    public function mapQueryException(QueryException $e): array
    {
        $message = $e->getMessage();
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        if ($this->isUniqueViolation($sqlState, $message)) {
            return [422, 'This value is already in use.'];
        }

        if ($this->isForeignKeyViolation($sqlState, $message)) {
            return [422, 'A related record is missing or cannot be used.'];
        }

        return [500, 'Unable to save. Please try again.'];
    }

    protected function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    protected function looksLikeSqlMessage(string $message): bool
    {
        return (bool) preg_match('/SQLSTATE|SQL:|Integrity constraint/i', $message);
    }

    protected function isUniqueViolation(string $sqlState, string $message): bool
    {
        if (in_array($sqlState, ['23000', '23505'], true)) {
            return (bool) preg_match('/unique|duplicate/i', $message);
        }

        return (bool) preg_match('/Duplicate entry|UNIQUE constraint failed|unique constraint/i', $message);
    }

    protected function isForeignKeyViolation(string $sqlState, string $message): bool
    {
        if (in_array($sqlState, ['23000', '23503'], true)) {
            return (bool) preg_match('/foreign key|FOREIGN KEY/i', $message);
        }

        return (bool) preg_match('/FOREIGN KEY constraint failed|foreign key constraint/i', $message);
    }
}
