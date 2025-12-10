<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Author;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuthorResource;
use App\Http\Requests\Api\Admin\AuthorRequest;

class AuthorController extends Controller
{
    /**
     * @Permissions("list_author", group="author", desc="List Author")
     */
    public function index()
    {
        $authors = Author::all();

        return AuthorResource::collection($authors);
    }

    /**
     * @Permissions("create_author", group="author", desc="Create Author")
     */
    public function store(AuthorRequest $request)
    {
        $author = Author::create($request->validated());

        return response()->json([
            'data' => AuthorResource::make($author),
            'message' => 'Author Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_author", group="author", desc="Show Author")
     */
    public function show(Author $author)
    {
        return AuthorResource::make($author);
    }

    /**
     * @Permissions("edit_author", group="author", desc="Edit Author")
     */
    public function update(AuthorRequest $request, Author $author)
    {
        $author->update($request->validated());

        return response()->json([
            'data' => AuthorResource::make($author),
            'message' => 'Author Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_author", group="author", desc="Delete Author")
     */
    public function destroy(Author $author)
    {
        $author->delete();

        return response()->json([
            'message' => 'Author Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_author_status", group="author", desc="Update Status")
     */
    public function updateStatus(Author $author)
    {
        $author->update([
            'status' => ! $author->status,
        ]);

        return response([
            'status' => $author->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
