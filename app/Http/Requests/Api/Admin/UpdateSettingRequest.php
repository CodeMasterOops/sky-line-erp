<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'alternate_name' => ['required', 'string', 'max:255'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'office_time' => ['nullable', 'string', 'max:255'],
            'primary_email' => ['nullable', 'email'],
            'secondary_email' => ['nullable', 'email'],
            'established_year' => ['nullable', 'numeric'],
            'logo' => ['nullable', 'string', 'max:255'],
            'favicon' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
            'facebook_link' => ['nullable', 'url'],
            'twitter_link' => ['nullable', 'url'],
            'instagram_link' => ['nullable', 'url'],
            'pinterest_link' => ['nullable', 'url'],
            'skype_link' => ['nullable', 'url'],
            'linkedin_link' => ['nullable', 'url'],
            'youtube_link' => ['nullable', 'url'],
            'google_map_link' => ['nullable', 'url'],
            'total_ratings' => ['nullable', 'numeric'],
            'rating_value' => ['nullable', 'numeric', 'between:0,5'],
            'meta_title' => ['nullable'],
            'meta_keywords' => ['nullable'],
            'meta_description' => ['nullable'],
            'blog_page_id' => ['nullable'],
            'contact_page_id' => ['nullable'],
            'story_page_id' => ['nullable'],
            'search_page_id' => ['nullable'],
            'sitemap_page_id' => ['nullable'],
            'featured_collection_id' => ['nullable', Rule::exists('collections', 'id')->withoutTrashed()],
            'new_in_collection_id' => ['nullable', Rule::exists('collections', 'id')->withoutTrashed()],
            'trending_collection_id' => ['nullable', Rule::exists('collections', 'id')->withoutTrashed()],
            'best_seller_collection_id' => ['nullable', Rule::exists('collections', 'id')->withoutTrashed()],
        ];
    }
}
