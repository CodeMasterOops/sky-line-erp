<?php

namespace App\Http\Requests\Api\Admin\UserManagement\Role;

use Illuminate\Validation\Rule;
use App\Services\PermissionRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $grantable = app(PermissionRegistry::class)->grantableFor($this->user('admin'));

        return [
            'name' => ['required', Rule::unique('roles')->withoutTrashed()->ignore($this->role)],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', 'string', Rule::in($grantable)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.*.in' => 'You are not allowed to grant one or more of the selected permissions.',
        ];
    }
}
