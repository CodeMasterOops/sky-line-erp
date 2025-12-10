<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Tag;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TagResource;
use App\Http\Requests\Api\Admin\TagRequest;

class TagController extends Controller
{
    /**
     * @Permissions("list_tag", group="tag", desc="List Tag")
     */
    public function index(Request $request)
    {
        $tags = Tag::filter($request->all())
            ->withCount('products')
            ->paginate($request->query('limit', 25));

        return TagResource::collection($tags);
    }

    /**
     * @Permissions("create_tag", group="tag", desc="Create Tag")
     */
    public function store(TagRequest $request)
    {
        $tag = Tag::create($request->validated());

        return response()->json([
            'data' => TagResource::make($tag),
            'message' => 'Tag Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_tag", group="tag", desc="Show Tag")
     */
    public function show(Tag $tag)
    {
        return TagResource::make($tag);
    }

    /**
     * @Permissions("edit_tag", group="tag", desc="Edit Tag")
     */
    public function update(TagRequest $request, Tag $tag)
    {
        $tag->update($request->validated());

        $tag->loadCount('products');

        return response()->json([
            'data' => TagResource::make($tag),
            'message' => 'Tag Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_tag", group="tag", desc="Delete Tag")
     */
    public function destroy(Tag $tag)
    {
        $tag->products()->detach();
        $tag->delete();

        return response()->json([
            'message' => 'Tag Deleted Successfully',
        ]);
    }
}
