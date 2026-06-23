<?php

namespace App\Http\Requests\Api\Admin\Crm;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'body' => ['required', 'string'],
        ];

        if ($this->isMethod('post')) {
            $rules['party_id'] = ['required', 'integer', TRule::exists('parties', 'id')];
        }

        return $rules;
    }
}
