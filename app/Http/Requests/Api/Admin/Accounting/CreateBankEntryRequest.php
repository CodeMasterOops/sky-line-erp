<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateBankEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contra_account_id' => ['required', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
        ];
    }
}
