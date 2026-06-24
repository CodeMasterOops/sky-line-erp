<?php

namespace App\Http\Controllers\Api\Admin\UserManagement;

use App\Annotation\Permissions;
use App\Traits\PermissionHelper;
use App\Http\Controllers\Controller;

class PermissionController extends Controller
{
    use PermissionHelper;

    #[Permissions('list_role')]
    #[Permissions('create_role')]
    #[Permissions('edit_role')]
    public function __invoke()
    {
        $permissions = $this->getAllPermissions();

        return response()->json([
            'data' => $permissions,
        ]);
    }
}
