<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Plan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\SuperAdmin\PlanResource;
use App\Http\Requests\Api\SuperAdmin\PlanRequest;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = Plan::query()
            ->withCount([
                'subscriptions',
                'subscriptions as active_subscriptions_count' => fn ($query) => $query->active(),
            ])
            ->filter($request->query())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->query('limit', 25));

        return PlanResource::collection($plans);
    }

    public function store(PlanRequest $request)
    {
        $data = $request->validated();

        if (! empty($data['is_default'])) {
            Plan::query()->update(['is_default' => false]);
        }

        $plan = Plan::create($data);

        return response()->json([
            'data' => PlanResource::make($plan),
            'message' => 'Plan created successfully.',
        ], 201);
    }

    public function show(Plan $plan)
    {
        $plan->loadCount([
            'subscriptions',
            'subscriptions as active_subscriptions_count' => fn ($query) => $query->active(),
        ]);

        return PlanResource::make($plan);
    }

    public function update(PlanRequest $request, Plan $plan)
    {
        if ($plan->is_default && array_key_exists('is_active', $request->validated()) && ! $request->boolean('is_active')) {
            throw ValidationException::withMessages([
                'is_active' => ['The default plan cannot be deactivated.'],
            ]);
        }

        $data = $request->validated();

        if (! empty($data['is_default'])) {
            Plan::query()->where('id', '!=', $plan->id)->update(['is_default' => false]);
        }

        $plan->update($data);

        return response()->json([
            'data' => PlanResource::make($plan->fresh()),
            'message' => 'Plan updated successfully.',
        ]);
    }

    public function destroy(Plan $plan)
    {
        if ($plan->is_default) {
            return response()->json([
                'message' => 'The default plan cannot be deleted.',
            ], 422);
        }

        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'message' => 'This plan cannot be deleted because it has subscriptions.',
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plan deleted successfully.',
        ]);
    }
}
