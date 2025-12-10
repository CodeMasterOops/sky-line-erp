<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Subscriber;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SubscriberResource;

class SubscriberController extends Controller
{
    /**
     * @Permissions("list_subscriber", group="subscriber", desc="List Subscriber")
     */
    public function index(Request $request)
    {
        $subscribers = Subscriber::filter($request->all())
            ->latest()
            ->paginate($request->query('limit', 25));

        return SubscriberResource::collection($subscribers);
    }

    /**
     * @Permissions("delete_subscriber", group="subscriber", desc="Delete Subscriber")
     */
    public function destroy(Subscriber $subscriber)
    {
        $subscriber->delete();

        return response()->json([
            'message' => 'Subscriber Deleted Successfully',
        ]);
    }
}
