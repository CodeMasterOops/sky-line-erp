<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Faq;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\FaqResource;
use App\Http\Requests\Api\Admin\FaqRequest;

class FaqController extends Controller
{
    /**
     * @Permissions("list_faq", group="faq", desc="List Faq")
     */
    public function index()
    {
        $faqs = Faq::whereNull('model_id')->orderBy('sort_order')->get();

        return FaqResource::collection($faqs);
    }

    /**
     * @Permissions("create_faq", group="faq", desc="Create Faq")
     */
    public function store(FaqRequest $request)
    {
        $faq = Faq::create($request->validated());

        return response()->json([
            'data' => FaqResource::make($faq),
            'message' => 'Faq Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_faq", group="faq", desc="Show Faq")
     */
    public function show(Faq $faq)
    {
        return FaqResource::make($faq);
    }

    /**
     * @Permissions("edit_faq", group="faq", desc="Edit Faq")
     */
    public function update(FaqRequest $request, Faq $faq)
    {
        $faq->update($request->validated());

        return response()->json([
            'data' => FaqResource::make($faq),
            'message' => 'Faq Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_faq", group="faq", desc="Delete Faq")
     */
    public function destroy(Faq $faq)
    {
        $faq->delete();

        return response()->json([
            'message' => 'Faq Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_faq_status", group="faq", desc="Update Status")
     */
    public function updateStatus(Faq $faq)
    {
        $faq->update([
            'status' => ! $faq->status,
        ]);

        return response([
            'status' => $faq->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
