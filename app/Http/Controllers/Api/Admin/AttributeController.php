<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Attribute;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AttributeResource;
use App\Http\Requests\Api\Admin\AttributeRequest;

class AttributeController extends Controller
{
    /**
     * @Permissions("list_attribute", group="attribute", desc="List Attribute")
     */
    public function index(Request $request)
    {
        $attributes = Attribute::with('values')->get();

        return AttributeResource::collection($attributes);
    }

    /**
     * @Permissions("create_attribute", group="attribute", desc="Create Attribute")
     */
    public function store(AttributeRequest $request)
    {
        $attribute = DB::transaction(function () use ($request) {
            $attribute = Attribute::create($request->validated());

            foreach ($request->validated('values', []) as $attrValue) {
                $attribute->values()->create($attrValue);
            }

            return $attribute;
        });

        $attribute->load('values');

        return response()->json([
            'data' => AttributeResource::make($attribute),
            'message' => 'Attribute Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_attribute", group="attribute", desc="Show Attribute")
     */
    public function show(Attribute $attribute)
    {
        $attribute->load([
            'values' => fn ($q) => $q->orderBy('sort_order'),
        ]);

        return AttributeResource::make($attribute);
    }

    /**
     * @Permissions("edit_attribute", group="attribute", desc="Edit Attribute")
     */
    public function update(AttributeRequest $request, Attribute $attribute)
    {
        DB::transaction(function () use ($request, $attribute) {
            $attribute->update($request->validated());

            $valueIds = array_filter(Arr::pluck($request->validated('values'), 'id'));

            if (count($valueIds)) {
                $attribute->values()->whereNotIn('id', $valueIds)->delete();
            }

            foreach ($request->validated('values', []) as $attrValue) {
                if (! empty($attrValue['id'])) {
                    $attribute->values()->where('id', $attrValue['id'])->update(Arr::except($attrValue, 'id'));
                } else {
                    $attribute->values()->create(Arr::except($attrValue, 'id'));
                }
            }
        });

        $attribute->load('values');

        return response()->json([
            'data' => AttributeResource::make($attribute),
            'message' => 'Attribute Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_attribute", group="attribute", desc="Delete Attribute")
     */
    public function destroy(Attribute $attribute)
    {
        $attribute->values()->delete();
        $attribute->delete();

        return response()->json([
            'message' => 'Attribute Deleted Successfully',
        ]);
    }
}
