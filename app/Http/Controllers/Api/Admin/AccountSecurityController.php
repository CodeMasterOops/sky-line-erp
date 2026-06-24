<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Enums\UserTypeEnum;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\SecurityActivityLogger;
use App\Http\Resources\Admin\DeviceResource;
use App\Http\Requests\Api\Admin\DeleteAccountRequest;
use App\Http\Resources\Admin\SecurityActivityResource;
use App\Http\Requests\Api\Admin\DeactivateAccountRequest;

class AccountSecurityController extends Controller
{
    public function __construct(private SecurityActivityLogger $activityLogger) {}

    public function activity(): JsonResponse
    {
        $activities = auth('admin')->user()
            ->securityActivities()
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);

        return SecurityActivityResource::collection($activities)->response();
    }

    public function devices(): JsonResponse
    {
        $user = auth('admin')->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $devices = $user->tokens()
            ->latest('last_used_at')
            ->get()
            ->each(function ($token) use ($currentTokenId): void {
                $token->is_current = $token->id === $currentTokenId;
            });

        return DeviceResource::collection($devices)->response();
    }

    public function revokeDevice(int $token): JsonResponse
    {
        $user = auth('admin')->user();

        if ($user->currentAccessToken()?->id === $token) {
            return response()->json([
                'message' => 'You cannot revoke the device you are currently using. Sign out instead.',
            ], 422);
        }

        $deleted = $user->tokens()->whereKey($token)->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Device not found.'], 404);
        }

        $this->activityLogger->log($user, 'device_revoked', 'Signed out a device');

        return response()->json(['message' => 'Device signed out successfully.']);
    }

    public function revokeOtherDevices(): JsonResponse
    {
        $user = auth('admin')->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        $this->activityLogger->log($user, 'device_revoked', 'Signed out all other devices');

        return response()->json(['message' => 'All other devices have been signed out.']);
    }

    public function deactivate(DeactivateAccountRequest $request): JsonResponse
    {
        $user = auth('admin')->user();

        if ($error = $this->lastAdminGuard($user, 'deactivate')) {
            return $error;
        }

        $user->update(['status' => false]);
        $this->activityLogger->log($user, 'deactivated', 'Account deactivated');
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Your account has been deactivated.',
        ]);
    }

    public function destroyAccount(DeleteAccountRequest $request): JsonResponse
    {
        $user = auth('admin')->user();

        if ($error = $this->lastAdminGuard($user, 'delete')) {
            return $error;
        }

        $this->activityLogger->log($user, 'account_deleted', 'Account deleted');
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Your account has been deleted.',
        ]);
    }

    /**
     * Block deactivating/deleting the only remaining active administrator of a
     * company, which would otherwise lock everyone out of the tenant.
     */
    private function lastAdminGuard(User $user, string $action): ?JsonResponse
    {
        if (! $user->isAdmin()) {
            return null;
        }

        $otherActiveAdmins = User::query()
            ->where('company_id', $user->company_id)
            ->where('user_type', UserTypeEnum::ADMIN)
            ->where('status', true)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($otherActiveAdmins) {
            return null;
        }

        return response()->json([
            'message' => "You are the only active administrator. Assign another admin before you can {$action} your account.",
        ], 422);
    }
}
