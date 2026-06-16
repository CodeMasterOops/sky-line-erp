<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Lead;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\LeadResource;
use App\Http\Requests\Api\SuperAdmin\LeadRequest;

class LeadController extends Controller
{
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $leads = Lead::query()
            ->filter($request->query())
            ->latest()
            ->paginate($request->query('limit', 25));

        return LeadResource::collection($leads);
    }

    public function show(Lead $lead): LeadResource
    {
        return LeadResource::make($lead);
    }

    public function update(LeadRequest $request, Lead $lead): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['follow_up_note']) && $data['follow_up_note'] !== $lead->follow_up_note) {
            $data['followed_up_at'] = now();
        }

        $lead->update($data);

        return response()->json([
            'data' => LeadResource::make($lead->fresh()),
            'message' => 'Lead updated successfully.',
        ]);
    }

    public function destroy(Lead $lead): \Illuminate\Http\JsonResponse
    {
        $lead->delete();

        return response()->json(['message' => 'Lead deleted successfully.']);
    }
}
