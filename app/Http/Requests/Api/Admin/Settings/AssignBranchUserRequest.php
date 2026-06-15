<?php

namespace App\Http\Requests\Api\Admin\Settings;

use App\Services\TenantService;
use Illuminate\Foundation\Http\FormRequest;

class AssignBranchUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'This user does not belong to this company.',
            'role_id.exists' => 'The selected role does not belong to this company.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure the user and role belong to the authenticated company
        $companyId = TenantService::companyId();

        $this->mergeIfMissing(['is_active' => true]);

        if ($this->user_id && $companyId) {
            $valid = \App\Models\User::withoutGlobalScopes()
                ->where('id', $this->user_id)
                ->where('company_id', $companyId)
                ->exists();

            if (! $valid) {
                $this->merge(['user_id' => null]);
            }
        }

        if ($this->role_id && $companyId) {
            $valid = \App\Models\Role::withoutGlobalScopes()
                ->where('id', $this->role_id)
                ->where('company_id', $companyId)
                ->exists();

            if (! $valid) {
                $this->merge(['role_id' => null]);
            }
        }
    }
}
