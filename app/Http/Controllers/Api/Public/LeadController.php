<?php

namespace App\Http\Controllers\Api\Public;

use App\Models\Lead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Public\LeadRequest;

class LeadController extends Controller
{
    public function store(LeadRequest $request): \Illuminate\Http\JsonResponse
    {
        // Honeypot: silently succeed without saving if the hidden field is filled
        if ($request->filled('website')) {
            return response()->json(['message' => 'Thank you! We will get back to you soon.'], 201);
        }

        Lead::create([
            ...$request->safe()->except('website'),
            'ip_address' => $request->ip(),
            'source' => 'website',
        ]);

        return response()->json(['message' => 'Thank you! We will get back to you soon.'], 201);
    }
}
