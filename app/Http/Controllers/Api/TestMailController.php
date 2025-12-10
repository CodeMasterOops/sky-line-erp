<?php

namespace App\Http\Controllers\Api;

use App\Mail\TestMail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;

class TestMailController extends Controller
{
    public function sendMail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Mail::to($request->input('email'))->send(new TestMail);

        return response()->json([
            'message' => 'Mail Sent Successfully',
        ]);
    }
}
