<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Customer;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CustomerResource;

class CustomerController extends Controller
{
    /**
     * @Permissions("list_customer", group="customer", desc="List Customer")
     */
    public function index()
    {
        $customers = Customer::latest()->get();

        return CustomerResource::collection($customers);
    }

    /**
     * @Permissions("show_customer", group="customer", desc="Show Customer")
     */
    public function show(Customer $customer)
    {
        return CustomerResource::make($customer);
    }

    /**
     * @Permissions("delete_customer", group="customer", desc="Delete Customer")
     */
    public function destroy(Customer $customer)
    {
        $customer->tokens()->delete();
        $customer->delete();

        return response()->json([
            'message' => 'Customer Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_customer_status", group="customer", desc="Update Status")
     */
    public function updateStatus(Customer $customer)
    {
        $customer->update([
            'status' => ! $customer->status,
        ]);

        return response([
            'status' => $customer->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
