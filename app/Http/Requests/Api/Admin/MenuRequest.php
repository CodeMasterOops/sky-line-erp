<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\MenuTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_type' => ['required', Rule::enum(MenuTypeEnum::class)],
            'parent_id' => ['nullable', Rule::exists('menus', 'id')->withoutTrashed()],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
