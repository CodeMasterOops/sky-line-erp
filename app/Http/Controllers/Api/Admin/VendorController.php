<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Vendor;
use App\Enums\UserTypeEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Enums\VendorStatusEnum;
use App\Mail\VendorRejectionMail;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Mail\VendorVerificationMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Http\Resources\Admin\VendorResource;
use App\Http\Requests\Api\Admin\VendorRequest;

class VendorController extends Controller
{
    /**
     * @Permissions("list_vendor", group="vendor", desc="List Vendor")
     */
    public function index(Request $request)
    {
        $vendors = Vendor::filter($request->query())
            ->with('admin')
            ->latest()
            ->paginate($request->query('limit', 25));

        return VendorResource::collection($vendors);
    }

    /**
     * @Permissions("create_vendor", group="vendor", desc="Create Vendor")
     */
    public function store(VendorRequest $request)
    {
        $formData = $request->validated();

        $formData['is_active'] = true;

        $vendor = DB::transaction(function () use ($formData) {
            $vendor = Vendor::create($formData);

            $vendor->admin()->create([
                'name' => $formData['user_name'],
                'email' => $formData['user_email'],
                'phone' => $formData['user_phone'] ?? null,
                'password' => bcrypt($formData['password']),
                'user_type' => UserTypeEnum::VENDOR_ADMIN->value,
            ]);

            return $vendor;
        });

        return response()->json([
            'data' => VendorResource::make($vendor),
            'message' => 'Vendor Created Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_vendor", group="vendor", desc="Show Vendor")
     */
    public function show(Vendor $vendor)
    {
        $vendor->load('admin');

        return VendorResource::make($vendor);
    }

    /**
     * @Permissions("edit_vendor", group="vendor", desc="Edit Vendor")
     */
    public function update(VendorRequest $request, Vendor $vendor)
    {
        $formData = $request->validated();

        $vendor = DB::transaction(function () use ($formData, $vendor) {
            $vendor->update($formData);
            $vendor->admin()->update([
                'name' => $formData['user_name'],
                'phone' => $formData['user_phone'],
                'email' => $formData['user_email'],
            ]);

            return $vendor;
        });

        return response()->json([
            'data' => VendorResource::make($vendor),
            'message' => 'Vendor Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_vendor", group="vendor", desc="Delete Vendor")
     */
    public function destroy(Vendor $vendor)
    {
        DB::transaction(function () use ($vendor) {
            $vendor->users()->delete();
            $vendor->delete();
        });

        return response()->json([
            'message' => 'Vendor Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_vendor_status", group="vendor", desc="Update Status")
     */
    public function updateStatus(Vendor $vendor)
    {
        foreach ($vendor->users as $user) {
            $user->tokens()->delete();
        }

        $vendor->update([
            'is_active' => ! $vendor->is_active,
        ]);

        $vendor->users()->update([
            'status' => ! $vendor->is_active,
        ]);

        return response([
            'is_active' => $vendor->is_active,
            'message' => 'Status updated successfully',
        ]);
    }

    /**
     * @Permissions("reset_vendor_password", group="vendor", desc="Reset Password")
     */
    public function resetPassword(Request $request, Vendor $vendor)
    {
        $request->validate([
            'password' => ['required', 'min:7', 'confirmed'],
        ]);

        $vendor->admin()->update([
            'password' => bcrypt($request->input('password')),
        ]);

        return response()->json([
            'data' => '',
            'message' => 'Vendor Password Successfully Reset.',
        ]);
    }

    /**
     * @Permissions("login_vendor", group="vendor", desc="Login Vendor")
     */
    public function vendorLogin(Vendor $vendor)
    {
        $user = $vendor->admin;

        if ($vendor->is_active) {
            Auth::guard('vendor')->setUser($user);

            $authUser = auth('vendor')->user();

            $authToken = $authUser->createToken('auth-token')->plainTextToken;

            return response()->json([
                'access_token' => $authToken,
                'message' => 'Signed In Successfully.',
            ]);
        } else {
            return response()->json([
                'message' => 'Vendor not active',
            ], 403);
        }
    }

    /**
     * @Permissions("verify_vendor_account", group="vendor", desc="Verify Vendor Account")
     */
    public function verifyVendor(Vendor $vendor)
    {
        $vendor->update([
            'vendor_status' => VendorStatusEnum::VERIFIED->value,
        ]);

        $mailData = [
            'vendor_name' => $vendor->vendor_name,
        ];

        Mail::to($vendor->email)->send(new VendorVerificationMail($mailData));

        return response([
            'message' => 'Vendor verified successfully',
        ]);
    }

    /**
     * @Permissions("reject_vendor_account", group="vendor", desc="Reject Vendor Account")
     */
    public function rejectVendor(Request $request, Vendor $vendor)
    {
        $request->validate([
            'reject_reason' => ['required', 'string'],
        ]);

        $vendor->update([
            'vendor_status' => VendorStatusEnum::REJECTED->value,
        ]);

        $mailData = [
            'vendor_name' => $vendor->vendor_name,
            'reject_reason' => $request->reject_reason,
        ];

        Mail::to($vendor->email)->send(new VendorRejectionMail($mailData));

        return response([
            'message' => 'Vendor rejected successfully',
        ]);
    }
}
