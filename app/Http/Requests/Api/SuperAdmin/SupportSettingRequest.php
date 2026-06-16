<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SupportSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'support_phones' => ['nullable', 'array'],
            'support_phones.*.label' => ['required', 'string', 'max:100'],
            'support_phones.*.number' => ['required', 'string', 'max:30'],

            'support_emails' => ['nullable', 'array'],
            'support_emails.*.label' => ['required', 'string', 'max:100'],
            'support_emails.*.address' => ['required', 'email', 'max:255'],

            'support_whatsapp' => ['nullable', 'array'],
            'support_whatsapp.*.label' => ['required', 'string', 'max:100'],
            'support_whatsapp.*.number' => ['required', 'string', 'max:30'],

            'support_social_links' => ['nullable', 'array'],
            'support_social_links.*.platform' => ['required', 'string', 'max:50'],
            'support_social_links.*.url' => ['required', 'url', 'max:500'],

            'support_videos' => ['nullable', 'array'],
            'support_videos.*.title' => ['required', 'string', 'max:255'],
            'support_videos.*.url' => ['required', 'url', 'max:500'],
        ];
    }
}
