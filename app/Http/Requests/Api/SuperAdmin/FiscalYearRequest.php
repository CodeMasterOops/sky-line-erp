<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class FiscalYearRequest extends FormRequest
{
    /** Nepal fiscal years run Shrawan 1 → Ashadh end (~364–366 days). */
    private const MIN_DAYS = 360;

    private const MAX_DAYS = 370;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => [
                'year_name' => ['required', 'string', 'max:255', Rule::unique('fiscal_years')->withoutTrashed()],
                'year_code' => ['required', 'string', 'max:255', Rule::unique('fiscal_years')->withoutTrashed()],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after:start_date'],
            ],
            'PUT' => [
                'year_name' => ['required', 'string', 'max:255', Rule::unique('fiscal_years')->withoutTrashed()->ignore($this->fiscal_year)],
                'year_code' => ['required', 'string', 'max:255', Rule::unique('fiscal_years')->withoutTrashed()->ignore($this->fiscal_year)],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after:start_date'],
            ]
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $start = $this->input('start_date');
            $end = $this->input('end_date');

            if (! $start || ! $end) {
                return;
            }

            $days = Carbon::parse($start)->diffInDays(Carbon::parse($end));

            if ($days < self::MIN_DAYS || $days > self::MAX_DAYS) {
                $v->errors()->add(
                    'end_date',
                    'A Nepal fiscal year must span '.self::MIN_DAYS.'–'.self::MAX_DAYS.' days '
                    ."(Shrawan 1 to Ashadh end ≈ 365 days). The entered range is {$days} days."
                );
            }
        });
    }
}
