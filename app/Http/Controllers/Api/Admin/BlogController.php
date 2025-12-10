<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Blog;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\Admin\BlogResource;
use App\Http\Requests\Api\Admin\BlogRequest;

class BlogController extends Controller
{
    /**
     * @Permissions("list_blog", group="blog", desc="List Blog")
     */
    public function index(Request $request)
    {
        $blogs = Blog::filter($request->all())
            ->with('authors')
            ->latest('publish_date')
            ->paginate($request->query('limit', 25));

        return BlogResource::collection($blogs);
    }

    /**
     * @Permissions("create_blog", group="blog", desc="Create Blog")
     */
    public function store(BlogRequest $request)
    {
        $blog = DB::transaction(function () use ($request) {
            $blog = Blog::create($request->validated());

            $blog->authors()->attach($request->validated('authors'));

            return $blog;
        });

        $this->purgeBlogCache($blog);

        return response()->json([
            'data' => BlogResource::make($blog),
            'message' => 'Blog Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_blog", group="blog", desc="Show Blog")
     */
    public function show(Blog $blog)
    {
        $blog->load([
            'authors',
        ]);

        return BlogResource::make($blog);
    }

    /**
     * @Permissions("edit_blog", group="blog", desc="Edit Blog")
     */
    public function update(BlogRequest $request, Blog $blog)
    {
        DB::transaction(function () use ($request, $blog) {
            $blog->update($request->validated());

            $blog->authors()->sync($request->validated('authors'));
        });

        $this->purgeBlogCache($blog);

        return response()->json([
            'data' => BlogResource::make($blog),
            'message' => 'Blog Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_blog", group="blog", desc="Delete Blog")
     */
    public function destroy(Blog $blog)
    {
        $blog->authors()->detach();
        $blog->delete();

        $this->purgeBlogCache($blog);

        return response()->json([
            'message' => 'Blog Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_blog_status", group="blog", desc="Update Status")
     */
    public function updateStatus(Blog $blog)
    {
        $blog->update([
            'status' => ! $blog->status,
        ]);

        $this->purgeBlogCache($blog);

        return response([
            'status' => $blog->status,
            'message' => 'Status updated successfully',
        ]);
    }

    private function purgeBlogCache($blog)
    {
        Cache::forget('home');
        Cache::forget('sitemap');
        Cache::forget('blogs_sitemap');

        $urls = [route('home')];
        flushCloudflareCache($urls);
        flushCloudflarePrefixCache(['api/blog']);
    }
}
