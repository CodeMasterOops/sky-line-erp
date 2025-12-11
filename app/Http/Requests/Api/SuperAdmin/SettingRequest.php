<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
            'facebook_link' => ['nullable', 'url'],
            'twitter_link' => ['nullable', 'url'],
            'instagram_link' => ['nullable', 'url'],
            'pinterest_link' => ['nullable', 'url'],
            'skype_link' => ['nullable', 'url'],
            'linkedin_link' => ['nullable', 'url'],
            'youtube_link' => ['nullable', 'url'],
            'google_map_link' => ['nullable', 'url'],
        ];
    }
}
