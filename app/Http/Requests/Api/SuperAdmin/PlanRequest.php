<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('plans', 'slug')->ignore($planId),
            ],
            'description' => ['nullable', 'string'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            // null / absent = the plan entitles every module. An explicit list
            // caps what companies on this plan may run; it never deletes data.
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(app(ModuleRegistry::class)->keys())],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'is_recommended' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'branch_limit' => ['nullable', 'integer', 'min:1'],
            // Quota keys are declared in config/limits.php; a null value is
            // unlimited, which is what every existing plan stays at.
            'limits' => ['nullable', 'array'],
            'limits.*' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge([
                'slug' => Str::slug($this->input('name')),
            ]);
        }
    }
}
