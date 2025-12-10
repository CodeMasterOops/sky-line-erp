<?php

namespace App\Http\Requests\Api\Admin\UserManagement\Role;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', Rule::unique('roles')->withoutTrashed()],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required'],
        ];
    }
}
