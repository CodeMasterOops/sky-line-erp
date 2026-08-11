<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload shape: {"modules": {"crm": true, "hr": false}, "cascade": false}.
 */
class UpdateCompanyModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['boolean'],
            // Switching a module off takes its dependents with it. Making the
            // caller ask for that explicitly means one careless toggle cannot
            // quietly remove half a company's navigation.
            'cascade' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $known = app(ModuleRegistry::class)->keys();

            foreach (array_keys((array) $this->input('modules', [])) as $moduleKey) {
                if (! in_array($moduleKey, $known, true)) {
                    $validator->errors()->add('modules', "Unknown module [{$moduleKey}].");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'modules.required' => 'Select at least one module to change.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('modules'))) {
            return;
        }

        $this->merge([
            'modules' => array_map(
                fn ($value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                $this->input('modules'),
            ),
        ]);
    }
}
