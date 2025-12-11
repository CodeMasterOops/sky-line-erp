<?php

namespace App\Http\Requests\Api\Admin\UserManagement\Role;

use App\Tenancy\TRule;
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
            'name' => ['required', TRule::unique('roles')->withoutTrashed()],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required'],
        ];
    }
}
