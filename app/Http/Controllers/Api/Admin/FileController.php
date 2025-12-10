<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\FileResource;
use App\Http\Requests\Api\Admin\FileRequest;

class FileController extends Controller
{
    /**
     * @Permissions("list_file", group="file", desc="List File")
     */
    public function index(Request $request)
    {
        $medias = File::filter($request->all())
            ->when(! empty($request->sort_by), function ($query) use ($request) {
                if ($request->sort_by == 'newest') {
                    $query->latest();
                }
            })->paginate($request->query('limit', 24));

        return FileResource::collection($medias);
    }

    /**
     * @Permissions("create_file", group="file", desc="Create File")
     */
    public function store(FileRequest $request)
    {
        $mediaList = collect();

        $folderId = $request->validated('folder_id');
        $folder = Folder::with('parent')->find($folderId);

        $imageAlt = $request->validated('image_alt');

        foreach ($request->validated('files') as $file) {
            $size = $file->getSize();
            $extension = $file->extension();
            $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $url = uploadFile($file, $folder);

            if ($url) {
                $media = File::create([
                    'folder_id' => $folderId,
                    'file_name' => $fileName,
                    'extension' => $extension,
                    'path' => $url,
                    'file_size' => $size,
                    'file_type' => $file->getClientMimeType(),
                ]);

                $mediaList->push($media);
            }
        }

        return response()->json([
            'data' => FileResource::collection($mediaList),
            'message' => 'File Added Successfully',
        ], 201);
    }

    public function show(File $file)
    {
        return FileResource::make($file);
    }

    /**
     * @Permissions("edit_file", group="file", desc="Edit File")
     */
    public function update(FileRequest $request, File $file)
    {
        $file->update($request->validated());

        return response()->json([
            'data' => FileResource::make($file),
            'message' => 'File Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_file", group="file", desc="Delete File")
     */
    public function destroy(File $file)
    {
        $file->delete();

        return response()->json([
            'message' => 'File Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("list_trashed_file", group="file", desc="List Trashed File")
     */
    public function trashed(Request $request)
    {
        $medias = File::when(! empty($request->sort_by), function ($query) use ($request) {
            if ($request->sort_by == 'newest') {
                $query->latest();
            }
        })
            ->when(! empty($request->search), function ($query) use ($request) {
                $key = '%'.trim($request->search).'%';
                $query->where(function ($q) use ($key) {
                    $q->where('file_name', 'like', $key);
                });
            })
            ->onlyTrashed()
            ->paginate($request->query('limit', 24));

        return FileResource::collection($medias);
    }

    /**
     * @Permissions("restore_trashed_file", group="file", desc="Restore Trashed File")
     */
    public function restore($id)
    {
        File::withTrashed()->find($id)->restore();

        return response()->json([
            'message' => 'File restored successfully',
        ]);
    }

    /**
     * @Permissions("permanently_delete_file", group="file", desc="Permanently Delete File")
     */
    public function deletePermanently($id)
    {
        $file = File::withTrashed()->find($id);

        if ($file->path) {
            deleteFile($file->path);
            $file->forceDelete();

            return response()->json([
                'message' => 'File deleted successfully',
            ]);
        }

        return response()->json([
            'message' => 'File not deleted.',
        ], 400);
    }
}
