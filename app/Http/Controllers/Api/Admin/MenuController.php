<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Menu;
use App\Enums\MenuTypeEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MenuResource;
use App\Http\Requests\Api\Admin\MenuRequest;

class MenuController extends Controller
{
    /**
     * @Permissions("list_menu", group="menu", desc="List Menu")
     */
    public function index(Request $request)
    {
        $menu_type = $request->query('menu_type', MenuTypeEnum::HEADER->value);

        $menus = Menu::with(['children' => function ($query) {
            $query->with('parent')->orderBy('sort_order');
        }])
            ->where('menu_type', $menu_type)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return MenuResource::collection($menus);
    }

    /**
     * @Permissions("create_menu", group="menu", desc="Create Menu")
     */
    public function store(MenuRequest $request)
    {
        $menu = Menu::create($request->validated());

        return response()->json([
            'data' => MenuResource::make($menu),
            'message' => 'Menu Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_menu", group="menu", desc="Show Menu")
     */
    public function show(Menu $menu)
    {
        return MenuResource::make($menu);
    }

    /**
     * @Permissions("edit_menu", group="menu", desc="Edit Menu")
     */
    public function update(MenuRequest $request, Menu $menu)
    {
        $menu->update($request->validated());

        return response()->json([
            'data' => MenuResource::make($menu),
            'message' => 'Menu Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_menu", group="menu", desc="Delete Menu")
     */
    public function destroy(Menu $menu)
    {
        $menu->delete();

        return response()->json([
            'message' => 'Menu Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_menu_status", group="menu", desc="Update Status")
     */
    public function updateStatus(Menu $menu)
    {
        $menu->update([
            'status' => ! $menu->status,
        ]);

        return response([
            'status' => $menu->status,
            'message' => 'Status updated successfully',
        ]);
    }

    /**
     * @Permissions("update_menu_order", group="menu", desc="Update Order")
     */
    public function updateSortOrder(Request $request, Menu $menu)
    {
        $formData = $request->validate([
            'parent_id' => ['nullable', Rule::exists('menus', 'id')->withoutTrashed()],
            'sort_order' => ['required', 'integer'],
        ]);

        $menu->update($formData);

        return response([
            'message' => 'Order updated successfully',
        ]);
    }
}
