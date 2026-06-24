<?php

namespace App\Http\Requests\Api\Admin\UserManagement\User;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\RestrictsAssignableRoles;

class StoreUserRequest extends FormRequest
{
    use RestrictsAssignableRoles;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')],
            'phone' => ['nullable'],
            'roles' => ['required', 'array', $this->assignableRolesRule()],
            'roles.*' => [Rule::exists('roles', 'id')->withoutTrashed()],
            'password' => ['required', 'min:6', 'confirmed'],
        ];
    }
}
