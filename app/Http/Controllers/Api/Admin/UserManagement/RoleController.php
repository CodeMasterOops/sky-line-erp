<?php

namespace App\Http\Controllers\Api\Admin\UserManagement;

use App\Models\Role;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserManagement\RoleResource;
use App\Http\Requests\Api\Admin\UserManagement\Role\StoreRoleRequest;
use App\Http\Requests\Api\Admin\UserManagement\Role\UpdateRoleRequest;

class RoleController extends Controller
{
    /**
     * @Permissions("list_role", group="role", desc="List Role")
     */
    public function index()
    {
        $roles = Role::all();

        return RoleResource::collection($roles);
    }

    /**
     * @Permissions("create_role", group="role", desc="Create Role")
     */
    public function store(StoreRoleRequest $request)
    {
        $role = Role::create($request->validated());

        return response()->json([
            'data' => RoleResource::make($role),
            'message' => 'Role Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_role", group="role", desc="Show Role")
     */
    public function show(Role $role)
    {
        return RoleResource::make($role);
    }

    /**
     * @Permissions("edit_role", group="role", desc="Edit Role")
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $role->update($request->validated());

        return response()->json([
            'data' => RoleResource::make($role),
            'message' => 'Role Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_role", group="role", desc="Delete Role")
     */
    public function destroy(Role $role)
    {
        $role->delete();

        return response()->json([
            'message' => 'Role Deleted Successfully',
        ]);
    }
}
