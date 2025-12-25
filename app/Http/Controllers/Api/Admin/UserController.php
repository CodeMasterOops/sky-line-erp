<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Enums\UserTypeEnum;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserManagement\UserResource;
use App\Http\Requests\Api\Admin\UserManagement\User\StoreUserRequest;
use App\Http\Requests\Api\Admin\UserManagement\User\UpdateUserRequest;

class UserController extends Controller
{
    /**
     * @Permissions("list_user", group="user", desc="List User")
     */
    public function index()
    {
        $users = User::with('roles:id,name')
            ->where('company_id', auth('admin')->user()->company_id)
            ->whereNot('user_type', UserTypeEnum::ADMIN->value)
            ->get();

        return UserResource::collection($users);
    }

    /**
     * @Permissions("create_user", group="user", desc="Create User")
     */
    public function store(StoreUserRequest $request)
    {
        $formData = $request->validated();
        $formData['company_id'] = auth('admin')->user()->company_id;

        $user = DB::transaction(function () use ($formData) {
            $user = User::create($formData);
            $user->roles()->attach($formData['roles']);

            return $user;
        });

        $user->load('roles:id,name');

        return response()->json([
            'data' => UserResource::make($user),
            'message' => 'User Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_user", group="user", desc="Show User")
     */
    public function show(User $user)
    {
        $user->load('roles');

        return UserResource::make($user);
    }

    /**
     * @Permissions("edit_user", group="user", desc="Edit User")
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        DB::transaction(function () use ($request, $user) {
            $user->update($request->validated());
            $user->roles()->sync($request->validated('roles'));
        });

        $user->load('roles:id,name');

        return response()->json([
            'data' => UserResource::make($user),
            'message' => 'User Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_user", group="user", desc="Delete User")
     */
    public function destroy(User $user)
    {
        if ($user->user_type !== UserTypeEnum::ADMIN) {
            $user->roles()->detach();
            $user->delete();

            return response()->json([
                'message' => 'User Deleted Successfully',
            ]);
        } else {
            return response()->json([
                'message' => 'Super admin can not be deleted.',
            ], 400);
        }
    }

    /**
     * @Permissions("update_user_status", group="user", desc="Update Status")
     */
    public function updateStatus(User $user)
    {
        $user->update([
            'status' => ! $user->status,
        ]);

        return response([
            'status' => $user->status,
            'message' => 'User status updated successfully',
        ]);
    }
}
