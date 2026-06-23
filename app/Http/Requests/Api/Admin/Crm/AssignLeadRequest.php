<?php

namespace App\Http\Requests\Api\Admin\Crm;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to_user_id' => ['required', 'integer', TRule::exists('users', 'id')],
        ];
    }
}
