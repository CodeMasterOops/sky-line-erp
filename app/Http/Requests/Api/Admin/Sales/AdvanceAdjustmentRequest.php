<?php

namespace App\Http\Requests\Api\Admin\Sales;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class AdvanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', TRule::exists('invoices', 'id')->withoutTrashed()],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
