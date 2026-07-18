<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\Enums\EntityCodeType;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Controllers\Concerns\GeneratesEntityCode;
use App\Http\Resources\Admin\Inventory\WarehouseResource;
use App\Http\Requests\Api\Admin\Inventory\WarehouseRequest;

class WarehouseController extends Controller
{
    use GeneratesEntityCode;

    #[Permissions('list_warehouse', group: 'warehouse', desc: 'List Warehouse')]
    public function index(Request $request)
    {
        $roots = Warehouse::query()
            ->whereNull('parent_id')
            ->orderBy('code')
            ->paginate($request->integer('limit', 25));

        $collection = $roots->getCollection()
            ->concat($this->descendantsOf($roots->getCollection()->modelKeys()));

        $collection->load('parent:id,name,code');
        $roots->setCollection($collection);

        return WarehouseResource::collection($roots);
    }

    /**
     * All descendant warehouses of the given parent ids, walked level by level.
     *
     * @param  array<int, int|string>  $parentIds
     */
    private function descendantsOf(array $parentIds): Collection
    {
        $descendants = new Collection;

        while (! empty($parentIds)) {
            $children = Warehouse::query()
                ->whereIn('parent_id', $parentIds)
                ->orderBy('code')
                ->get();

            if ($children->isEmpty()) {
                break;
            }

            $descendants = $descendants->concat($children);
            $parentIds = $children->modelKeys();
        }

        return $descendants;
    }

    #[Permissions('create_warehouse', group: 'warehouse', desc: 'Create Warehouse')]
    public function nextCode()
    {
        return $this->nextCodeResponse(EntityCodeType::Warehouse);
    }

    #[Permissions('create_warehouse', group: 'warehouse', desc: 'Create Warehouse')]
    public function store(WarehouseRequest $request)
    {
        $data = $request->validated();
        $this->assignEntityCode($data, EntityCodeType::Warehouse);
        $warehouse = Warehouse::create($data);
        $warehouse->load('parent:id,name,code');

        return response()->json([
            'data' => WarehouseResource::make($warehouse),
            'message' => 'Warehouse Added Successfully',
        ], 201);
    }

    #[Permissions('show_warehouse', group: 'warehouse', desc: 'Show Warehouse')]
    public function show(Warehouse $warehouse)
    {
        $warehouse->load('parent:id,name,code');

        return WarehouseResource::make($warehouse);
    }

    #[Permissions('edit_warehouse', group: 'warehouse', desc: 'Edit Warehouse')]
    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse->update($request->validated());
        $warehouse->load('parent:id,name,code');

        return response()->json([
            'data' => WarehouseResource::make($warehouse),
            'message' => 'Warehouse Updated Successfully',
        ]);
    }

    #[Permissions('delete_warehouse', group: 'warehouse', desc: 'Delete Warehouse')]
    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->children()->exists()) {
            return response()->json([
                'message' => __('Cannot delete a warehouse that has sub-warehouses. Remove or reassign them first.'),
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse Deleted Successfully',
        ]);
    }
}
