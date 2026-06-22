<?php

namespace App\Http\Controllers\Api\Admin\Crm;

use App\Models\Note;
use App\Models\Party;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Enums\CrmActivityTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\Crm\ActivityLogger;
use App\Http\Resources\Admin\Crm\NoteResource;
use App\Services\Crm\CustomerProfileAggregator;
use App\Http\Requests\Api\Admin\Crm\NoteRequest;

class NoteController extends Controller
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private CustomerProfileAggregator $aggregator,
    ) {}

    #[Permissions('list_crm_note', group: 'crm_note', desc: 'List Notes')]
    public function index(Request $request)
    {
        $notes = Note::query()
            ->where('notable_type', Party::class)
            ->where('notable_id', $request->integer('party_id'))
            ->with('user')
            ->latest()
            ->paginate($request->limit ?? 25);

        return NoteResource::collection($notes);
    }

    #[Permissions('create_crm_note', group: 'crm_note', desc: 'Create Note')]
    public function store(NoteRequest $request)
    {
        $party = Party::findOrFail($request->integer('party_id'));

        $note = $party->notes()->create([
            'user_id' => auth('admin')->id(),
            'body' => $request->validated()['body'],
        ]);

        $this->activityLogger->log($party, CrmActivityTypeEnum::NoteAdded, 'Note added');
        $this->aggregator->forget($party->id);

        return response()->json([
            'data' => NoteResource::make($note->load('user')),
            'message' => 'Note Added Successfully',
        ], 201);
    }

    #[Permissions('edit_crm_note', group: 'crm_note', desc: 'Edit Note')]
    public function update(NoteRequest $request, Note $note)
    {
        $note->update(['body' => $request->validated()['body']]);

        return response()->json([
            'data' => NoteResource::make($note->load('user')),
            'message' => 'Note Updated Successfully',
        ]);
    }

    #[Permissions('delete_crm_note', group: 'crm_note', desc: 'Delete Note')]
    public function destroy(Note $note)
    {
        $note->delete();

        return response()->json([
            'message' => 'Note Deleted Successfully',
        ]);
    }
}
