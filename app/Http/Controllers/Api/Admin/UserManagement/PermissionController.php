<?php

namespace App\Http\Controllers\Api\Admin\UserManagement;

use App\Traits\PermissionHelper;
use App\Http\Controllers\Controller;

class PermissionController extends Controller
{
    use PermissionHelper;

    public function __invoke()
    {
        $permissions = $this->getAllPermissions();

        return response()->json([
            'data' => $permissions,
        ]);
    }
}
