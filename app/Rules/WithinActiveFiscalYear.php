<?php

namespace App\Rules;

use Closure;
use Carbon\Carbon;
use App\Models\FiscalYear;
use App\Services\Nepal\DateDisplayService;
use Illuminate\Contracts\Validation\ValidationRule;

class WithinActiveFiscalYear implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $company = auth('admin')->user()?->company;
        if (! $company?->fiscal_year_id) {
            return;
        }

        $fiscalYear = FiscalYear::find($company->fiscal_year_id);
        if (! $fiscalYear?->start_date || ! $fiscalYear?->end_date) {
            return;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return;
        }

        $start = Carbon::parse($fiscalYear->start_date)->startOfDay();
        $end = Carbon::parse($fiscalYear->end_date)->endOfDay();

        if ($date->lt($start) || $date->gt($end)) {
            $display = app(DateDisplayService::class);

            $fail(__('The :attribute must fall within the active fiscal year (:start to :end).', [
                'attribute' => str_replace('_', ' ', $attribute),
                'start' => $display->format($start),
                'end' => $display->format($end),
            ]));
        }
    }
}
