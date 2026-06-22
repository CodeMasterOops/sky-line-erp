<?php

namespace App\Http\Controllers\Api\Admin;

use RuntimeException;
use Illuminate\Http\JsonResponse;
use App\Services\Pos\QzTrayService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\QzSignRequest;

class QzTrayController extends Controller
{
    public function __construct(protected QzTrayService $qzTray) {}

    /**
     * Return the public digital certificate the browser presents to QZ Tray.
     */
    public function certificate(): JsonResponse
    {
        return response()->json([
            'configured' => $this->qzTray->isConfigured(),
            'certificate' => $this->qzTray->certificate(),
        ]);
    }

    /**
     * Sign a QZ Tray request payload with the server-held private key.
     */
    public function sign(QzSignRequest $request): JsonResponse
    {
        try {
            $signature = $this->qzTray->sign($request->string('request')->toString());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['signature' => $signature]);
    }
}
