<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Enums\TaxTypeEnum;
use App\Enums\TdsCategoryEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class TaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typeValues = collect(TaxTypeEnum::cases())->pluck('value')->toArray();
        $tdsCategoryValues = collect(TdsCategoryEnum::cases())->pluck('value')->toArray();

        $baseRules = [
            'name' => ['required', 'string', 'max:255'],
            'rate' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                function ($attribute, $value, $fail) {
                    $type = $this->input('type');
                    $category = $this->input('tds_category');

                    if ($type !== TaxTypeEnum::TDS->value || ! $category) {
                        return;
                    }

                    $enumCase = TdsCategoryEnum::tryFrom($category);
                    if (! $enumCase) {
                        return;
                    }

                    $canonicalRate = $enumCase->rate();
                    if (abs((float) $value - $canonicalRate) > 0.001) {
                        $fail(sprintf(
                            'The rate for TDS category "%s" must be %s%% as defined by Nepal tax regulations.',
                            $enumCase->label(),
                            $canonicalRate
                        ));
                    }
                },
            ],
            'type' => ['nullable', Rule::in($typeValues)],
            'tds_category' => ['nullable', Rule::in($tdsCategoryValues)],
        ];

        if ($this->method() === 'POST') {
            $baseRules['name'][] = TRule::unique('taxes')->withoutTrashed();
        } else {
            $baseRules['name'][] = TRule::unique('taxes')->withoutTrashed()->ignore($this->tax);
        }

        return $baseRules;
    }
}
