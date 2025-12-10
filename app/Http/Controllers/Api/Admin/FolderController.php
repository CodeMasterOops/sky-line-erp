<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Folder;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\FolderResource;
use App\Http\Requests\Api\Admin\FolderRequest;

class FolderController extends Controller
{
    /**
     * @Permissions("list_folder", group="folder", desc="List Folder")
     */
    public function index(Request $request)
    {
        $folders = Folder::where(function ($query) use ($request) {
            if ($request->query('folder_id')) {
                $query->where('parent_id', $request->query('folder_id'));
            } else {
                $query->whereNull('parent_id');
            }
        })->get();

        $currentFolder = $request->query('folder_id') ? Folder::find($request->query('folder_id')) : null;

        return response()->json([
            'self' => $currentFolder ? FolderResource::make($currentFolder) : null,
            'data' => FolderResource::collection($folders),
        ]);
    }

    /**
     * @Permissions("create_folder", group="folder", desc="Create Folder")
     */
    public function store(FolderRequest $request)
    {
        $folder = Folder::create($request->validated());

        return response()->json([
            'data' => FolderResource::make($folder),
            'message' => 'Folder Added Successfully',
        ], 201);
    }

    public function show(Folder $folder)
    {
        return FolderResource::make($folder);
    }

    /**
     * @Permissions("edit_folder", group="folder", desc="Edit Folder")
     */
    public function update(FolderRequest $request, Folder $folder)
    {
        $folder->update($request->validated());

        return response()->json([
            'data' => FolderResource::make($folder),
            'message' => 'Folder Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_folder", group="folder", desc="Delete Folder")
     */
    public function destroy(Folder $folder)
    {
        $folder->delete();

        return response()->json([
            'message' => 'Folder Deleted Successfully',
        ]);
    }
}
