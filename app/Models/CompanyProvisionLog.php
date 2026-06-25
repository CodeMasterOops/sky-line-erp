<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyProvisionLog extends Model
{
    protected $fillable = [
        'company_id',
        'status',
        'step_results',
        'error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'step_results' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function startFor(Company $company): static
    {
        return static::create([
            'company_id' => $company->id,
            'status' => 'running',
            'step_results' => [],
            'started_at' => now(),
        ]);
    }

    public function recordStep(string $name, callable $callback): void
    {
        $start = microtime(true);

        try {
            $callback();
            $this->appendStepResult($name, 'complete', (int) round((microtime(true) - $start) * 1000));
        } catch (\Throwable $e) {
            $this->appendStepResult($name, 'failed', (int) round((microtime(true) - $start) * 1000), $e->getMessage());
            throw $e;
        }
    }

    public function recordStepFailure(string $name, \Throwable $e): void
    {
        $this->appendStepResult($name, 'failed', 0, $e->getMessage());
    }

    public function markComplete(): void
    {
        $this->update(['status' => 'complete', 'completed_at' => now()]);
    }

    public function markFailed(\Throwable $e): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
            'completed_at' => now(),
        ]);
    }

    public function countCompletedSteps(): int
    {
        return collect($this->step_results ?? [])->where('status', 'complete')->count();
    }

    private function appendStepResult(string $name, string $status, int $durationMs, ?string $error = null): void
    {
        $results = $this->step_results ?? [];
        $entry = ['name' => $name, 'status' => $status, 'duration_ms' => $durationMs];
        if ($error !== null) {
            $entry['error'] = $error;
        }
        $results[] = $entry;
        $this->update(['step_results' => $results]);
    }
}
