<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\TaxTypeEnum;
use App\Enums\TdsCategoryEnum;
use App\Models\TaxTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TaxTemplateController extends Controller
{
    public function index()
    {
        $templates = TaxTemplate::orderBy('name')->get()->map(function ($t) {
            return [
                'id'               => $t->id,
                'name'             => $t->name,
                'rate'             => $t->rate,
                'type'             => $t->type?->value,
                'type_label'       => $t->type?->label(),
                'tds_category'     => $t->tds_category?->value,
                'tds_category_label' => $t->tds_category?->label(),
                'is_default'       => $t->is_default,
                'description'      => $t->description,
            ];
        });

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request)
    {
        $typeValues = collect(TaxTypeEnum::cases())->pluck('value')->toArray();
        $tdsCategoryValues = collect(TdsCategoryEnum::cases())->pluck('value')->toArray();

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'rate'         => ['required', 'numeric', 'min:0', 'max:100'],
            'type'         => ['nullable', 'in:' . implode(',', $typeValues)],
            'tds_category' => ['nullable', 'in:' . implode(',', $tdsCategoryValues)],
            'is_default'   => ['boolean'],
            'description'  => ['nullable', 'string', 'max:500'],
        ]);

        $template = TaxTemplate::create($validated);

        return response()->json([
            'data'    => $template,
            'message' => 'Tax template created.',
        ], 201);
    }

    public function show(TaxTemplate $taxTemplate)
    {
        return response()->json(['data' => $taxTemplate]);
    }

    public function update(Request $request, TaxTemplate $taxTemplate)
    {
        $typeValues = collect(TaxTypeEnum::cases())->pluck('value')->toArray();
        $tdsCategoryValues = collect(TdsCategoryEnum::cases())->pluck('value')->toArray();

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'rate'         => ['required', 'numeric', 'min:0', 'max:100'],
            'type'         => ['nullable', 'in:' . implode(',', $typeValues)],
            'tds_category' => ['nullable', 'in:' . implode(',', $tdsCategoryValues)],
            'is_default'   => ['boolean'],
            'description'  => ['nullable', 'string', 'max:500'],
        ]);

        $taxTemplate->update($validated);

        return response()->json([
            'data'    => $taxTemplate->fresh(),
            'message' => 'Tax template updated.',
        ]);
    }

    public function destroy(TaxTemplate $taxTemplate)
    {
        $taxTemplate->delete();

        return response()->json(['message' => 'Tax template deleted.']);
    }
}
