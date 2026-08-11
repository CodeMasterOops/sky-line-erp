<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Services\TenantService;
use App\Http\Controllers\Controller;
use App\Services\BranchAccessService;
use Illuminate\Support\Facades\Storage;
use App\Services\SecurityActivityLogger;
use App\Http\Resources\Admin\ProfileResource;
use App\Services\Modules\CompanyModuleService;
use App\Http\Requests\Api\Admin\UpdateProfileRequest;
use App\Http\Requests\Api\Admin\ChangePasswordRequest;
use App\Http\Requests\Api\Admin\UpdateDateModeRequest;
use App\Http\Requests\Api\Admin\UpdatePinnedLinksRequest;
use App\Http\Requests\Api\Admin\UpdateReportPinnedLinksRequest;
use App\Http\Requests\Api\Admin\UpdateSidebarPinnedLinksRequest;

class ProfileController extends Controller
{
    public function profile()
    {
        $authUser = auth('admin')->user()->load('company');

        return response()->json([
            'data' => ProfileResource::make($authUser),
            'permissions' => base64_encode(json_encode(userPermissions($authUser))),
        ]);
    }

    /**
     * Return the current user's effective permissions and user_type for the active branch context.
     * Called by the frontend on page load and after a branch switch so the nav menu updates correctly.
     */
    public function permissions()
    {
        $user = auth('admin')->user();
        $branchId = TenantService::branchId();

        if ($branchId) {
            $permissions = app(BranchAccessService::class)->getEffectivePermissions($user, $branchId);
            // Admin wildcard → return all company permissions for UI rendering
            if ($permissions === ['*']) {
                $permissions = userPermissions($user);
            }
        } else {
            $permissions = userPermissions($user);
        }

        return response()->json([
            'permissions' => base64_encode(json_encode($permissions)),
            'user_type' => $user->user_type,
            // Ships with the permissions the SPA already fetches at boot and
            // after every branch switch, so module-aware navigation costs no
            // extra round trip. Richer per-module detail lives on
            // GET /api/admin/module.
            'modules' => $user->company_id
                ? app(CompanyModuleService::class)->enabledKeys((int) $user->company_id)
                : [],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        if ($request->hasFile('profile_photo')) {
            if ($photo = auth('admin')->user()->getRawOriginal('profile_photo')) {
                Storage::disk('public')->delete($photo);
            }
        }
        auth('admin')->user()->update($request->validated());

        return response()->json([
            'data' => ProfileResource::make(auth('admin')->user()),
            'message' => 'Profile Updated Successfully',
        ]);
    }

    public function updateDateMode(UpdateDateModeRequest $request)
    {
        auth('admin')->user()->update($request->validated());

        return response()->json([
            'data' => ProfileResource::make(auth('admin')->user()),
            'message' => 'Date preference updated successfully',
        ]);
    }

    public function updatePinnedLinks(UpdatePinnedLinksRequest $request)
    {
        auth('admin')->user()->update([
            'dashboard_pinned_links' => $request->validated('links'),
        ]);

        return response()->json([
            'data' => ProfileResource::make(auth('admin')->user()),
            'message' => 'Quick links updated successfully',
        ]);
    }

    public function updateReportPinnedLinks(UpdateReportPinnedLinksRequest $request)
    {
        auth('admin')->user()->update([
            'report_pinned_links' => $request->validated('links'),
        ]);

        return response()->json([
            'data' => ProfileResource::make(auth('admin')->user()),
            'message' => 'Pinned reports updated successfully',
        ]);
    }

    public function updateSidebarPinnedLinks(UpdateSidebarPinnedLinksRequest $request)
    {
        auth('admin')->user()->update([
            'sidebar_pinned_links' => $request->validated('links'),
        ]);

        return response()->json([
            'data' => ProfileResource::make(auth('admin')->user()),
            'message' => 'Sidebar links updated successfully',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = auth('admin')->user();
        $user->update($request->validated());

        // Revoke all tokens so stale sessions are invalidated after a password change.
        $user->tokens()->delete();

        $abilities = $this->buildTokenAbilities($user);
        $tokenData = $user->createToken('auth-token', $abilities, now()->addWeek());
        $tokenData->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
        ])->save();

        app(SecurityActivityLogger::class)->log($user, 'password_changed', 'Password changed');

        return response()->json([
            'access_token' => $tokenData->plainTextToken,
            'expires_at' => $tokenData->accessToken->expires_at,
            'message' => 'Password Changed Successfully',
        ]);
    }

    private function buildTokenAbilities(User $user): array
    {
        if ($user->isAdmin()) {
            return ['*'];
        }

        $permissions = userPermissions($user);

        return $permissions ?: ['user:read'];
    }
}
