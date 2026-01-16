<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Party;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PartyResource;
use App\Http\Requests\Api\Admin\PartyRequest;

class PartyController extends Controller
{
    /**
     * @Permissions("list_party", group="party", desc="List Party")
     */
    public function index(Request $request)
    {
        $parties = Party::filter($request->all())
            ->paginate($request->limit ?? 25);

        return PartyResource::collection($parties);
    }

    /**
     * @Permissions("create_party", group="party", desc="Create Party")
     */
    public function store(PartyRequest $request)
    {
        $party = Party::create($request->validated());

        return response()->json([
            'data' => PartyResource::make($party),
            'message' => 'Party Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_party", group="party", desc="Show Party")
     */
    public function show(Party $party)
    {
        return PartyResource::make($party);
    }

    /**
     * @Permissions("edit_party", group="party", desc="Edit Party")
     */
    public function update(PartyRequest $request, Party $party)
    {
        $party->update($request->validated());

        return response()->json([
            'data' => PartyResource::make($party),
            'message' => 'Party Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_party", group="party", desc="Delete Party")
     */
    public function destroy(Party $party)
    {
        $party->delete();

        return response()->json([
            'message' => 'Party Deleted Successfully',
        ]);
    }
}
