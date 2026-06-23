<?php

namespace App\Http\Controllers\Api\Admin\Crm;

use Illuminate\Http\Request;
use App\Models\ContactPerson;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Crm\ContactPersonResource;
use App\Http\Requests\Api\Admin\Crm\ContactPersonRequest;

class ContactPersonController extends Controller
{
    #[Permissions('list_crm_contact', group: 'crm_contact', desc: 'List Contact Persons')]
    public function index(Request $request)
    {
        $contacts = ContactPerson::filter($request->all())
            ->latest()
            ->paginate($request->limit ?? 25);

        return ContactPersonResource::collection($contacts);
    }

    #[Permissions('create_crm_contact', group: 'crm_contact', desc: 'Create Contact Person')]
    public function store(ContactPersonRequest $request)
    {
        $contact = ContactPerson::create($request->validated());

        return response()->json([
            'data' => ContactPersonResource::make($contact),
            'message' => 'Contact Person Added Successfully',
        ], 201);
    }

    #[Permissions('edit_crm_contact', group: 'crm_contact', desc: 'Edit Contact Person')]
    public function update(ContactPersonRequest $request, ContactPerson $contactPerson)
    {
        $contactPerson->update($request->validated());

        return response()->json([
            'data' => ContactPersonResource::make($contactPerson),
            'message' => 'Contact Person Updated Successfully',
        ]);
    }

    #[Permissions('delete_crm_contact', group: 'crm_contact', desc: 'Delete Contact Person')]
    public function destroy(ContactPerson $contactPerson)
    {
        $contactPerson->delete();

        return response()->json([
            'message' => 'Contact Person Deleted Successfully',
        ]);
    }
}
