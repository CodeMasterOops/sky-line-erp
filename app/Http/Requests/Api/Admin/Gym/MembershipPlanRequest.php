<?php

namespace App\Http\Requests\Api\Admin\Gym;

use App\Tenancy\TRule;
use App\Enums\DurationUnitEnum;
use Illuminate\Validation\Rule;
use App\Enums\MembershipDurationPresetEnum;
use Illuminate\Foundation\Http\FormRequest;

class MembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH'], true);
        $planId = $isUpdate ? $this->route('membershipPlan')?->id : null;

        return [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', TRule::unique('membership_plans', 'code')->ignore($planId)],
            'description' => ['nullable', 'string', 'max:1000'],

            // A preset fills the term in; `custom` (or no preset at all) means
            // the caller supplies unit + value themselves.
            'preset' => ['nullable', Rule::enum(MembershipDurationPresetEnum::class)],
            'duration_unit' => ['nullable', Rule::enum(DurationUnitEnum::class)],
            'duration_value' => ['nullable', 'integer', 'min:1', 'max:120'],

            'price' => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'joining_fee' => ['nullable', 'numeric', 'min:0'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'max_freeze_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $preset = $this->input('preset');
            $isCustom = $preset === null || $preset === MembershipDurationPresetEnum::Custom->value;

            if ($isCustom && $this->method() === 'POST' && ! $this->filled('duration_value')) {
                $validator->errors()->add(
                    'duration_value',
                    'Choose a standard term, or give the number of units for a custom one.',
                );
            }
        });
    }
}
