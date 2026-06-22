<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Carbon\Carbon;
use App\Models\FiscalYear;
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

            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);
            $days = $startDate->diffInDays($endDate);

            if ($days < self::MIN_DAYS || $days > self::MAX_DAYS) {
                $v->errors()->add(
                    'end_date',
                    'A Nepal fiscal year must span '.self::MIN_DAYS.'–'.self::MAX_DAYS.' days '
                    ."(Shrawan 1 to Ashadh end ≈ 365 days). The entered range is {$days} days."
                );
            }

            $this->assertNoOverlap($v, $startDate, $endDate);
        });
    }

    private function assertNoOverlap($v, Carbon $start, Carbon $end): void
    {
        $excludeId = $this->fiscal_year?->id;

        $overlap = FiscalYear::withoutTrashed()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_date', '<=', $start->toDateString())
                            ->where('end_date', '>=', $end->toDateString());
                    });
            })
            ->first(['id', 'year_name']);

        if ($overlap) {
            $v->errors()->add(
                'start_date',
                "This date range overlaps with fiscal year \"{$overlap->year_name}\". Fiscal years must not overlap."
            );
        }
    }
}
