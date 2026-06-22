<?php

namespace App\Http\Controllers\Api\Admin\Crm;

use App\Models\Party;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Crm\CrmActivityResource;

class TimelineController extends Controller
{
    /**
     * Read-only CRM-native activity feed for a party (customer or lead).
     * Financial events (invoices, payments) are merged in a later phase.
     */
    #[Permissions('view_crm_timeline', group: 'crm_customer', desc: 'View Customer Timeline')]
    public function index(Request $request, Party $party)
    {
        $activities = $party->activities()
            ->with('causer')
            ->paginate($request->limit ?? 25);

        return CrmActivityResource::collection($activities);
    }
}
