<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Collection;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CollectionResource;
use App\Http\Requests\Api\Admin\CollectionRequest;

class CollectionController extends Controller
{
    /**
     * @Permissions("list_collection", group="collection", desc="List Collection")
     */
    public function index()
    {
        $collections = Collection::orderBy('sort_order')->get();

        return CollectionResource::collection($collections);
    }

    /**
     * @Permissions("create_collection", group="collection", desc="Create Collection")
     */
    public function store(CollectionRequest $request)
    {
        $collection = DB::transaction(function () use ($request) {
            $collection = Collection::create($request->validated());

            $collection->products()->attach($request->validated('products', []));

            return $collection;
        });

        return response()->json([
            'data' => CollectionResource::make($collection),
            'message' => 'Collection Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_collection", group="collection", desc="Show Collection")
     */
    public function show(Collection $collection)
    {
        $collection->load('products:id,name');

        return CollectionResource::make($collection);
    }

    /**
     * @Permissions("edit_collection", group="collection", desc="Edit Collection")
     */
    public function update(CollectionRequest $request, Collection $collection)
    {
        DB::transaction(function () use ($request, $collection) {
            $collection->update($request->validated());

            $collection->products()->sync($request->validated('products', []));
        });

        return response()->json([
            'data' => CollectionResource::make($collection),
            'message' => 'Collection Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_collection", group="collection", desc="Delete Collection")
     */
    public function destroy(Collection $collection)
    {
        $collection->products()->detach();
        $collection->delete();

        return response()->json([
            'message' => 'Collection Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_collection_status", group="collection", desc="Update Status")
     */
    public function updateStatus(Collection $collection)
    {
        $collection->update([
            'status' => ! $collection->status,
        ]);

        return response([
            'status' => $collection->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
