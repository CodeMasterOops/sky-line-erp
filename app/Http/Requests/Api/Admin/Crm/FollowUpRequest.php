<?php

namespace App\Http\Requests\Api\Admin\Crm;

use App\Tenancy\TRule;
use Illuminate\Validation\Rule;
use App\Enums\FollowUpStatusEnum;
use App\Enums\FollowUpChannelEnum;
use Illuminate\Foundation\Http\FormRequest;

class FollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party_id' => ['required', 'integer', TRule::exists('parties', 'id')],
            'user_id' => ['nullable', 'integer', TRule::exists('users', 'id')],
            'channel' => ['required', Rule::enum(FollowUpChannelEnum::class)],
            'scheduled_at' => ['required', 'date'],
            'status' => ['nullable', Rule::enum(FollowUpStatusEnum::class)],
            'outcome' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }
}
