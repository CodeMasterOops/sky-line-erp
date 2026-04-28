<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use App\Services\BarcodeService;
use App\Http\Controllers\Controller;

class BarcodeController extends Controller
{
    public function __construct(private BarcodeService $barcodeService) {}

    /**
     * Generate and stream a PDF sheet of barcode labels.
     *
     * POST /api/admin/barcode/pdf
     *
     * Body:
     * {
     *   "paper":        "a4" | "letter" | "roll",
     *   "show_name":    true,
     *   "show_price":   true,
     *   "show_company": true,
     *   "show_value":   true,
     *   "items": [
     *     {
     *       "value":  "SKU-001",
     *       "format": "code128" | "ean13" | "qr",
     *       "qty":    3,
     *       "name":   "Product Name",
     *       "price":  "99.99"
     *     }
     *   ]
     * }
     */
    public function pdf(Request $request): Response
    {
        $validated = $request->validate([
            'paper' => ['nullable', Rule::in(['a4', 'letter', 'roll'])],
            'show_name' => ['nullable', 'boolean'],
            'show_price' => ['nullable', 'boolean'],
            'show_company' => ['nullable', 'boolean'],
            'show_value' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.value' => ['required', 'string', 'max:128'],
            'items.*.format' => ['required', Rule::in(['code128', 'ean13', 'qr'])],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:100'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.price' => ['nullable', 'string', 'max:32'],
        ]);

        $config = [
            'show_name' => (bool) ($validated['show_name'] ?? true),
            'show_price' => (bool) ($validated['show_price'] ?? true),
            'show_company' => (bool) ($validated['show_company'] ?? true),
            'show_value' => (bool) ($validated['show_value'] ?? true),
        ];

        $paper = $validated['paper'] ?? 'a4';
        $company = auth()->user()->company?->name ?? '';

        $labels = $this->barcodeService->buildLabelData($validated['items'], $config, $company);
        $pdf = $this->barcodeService->generatePdf($labels, $paper);

        return $pdf->download('barcode-labels.pdf');
    }

    /**
     * Generate a single barcode SVG inline (used for live preview fallback).
     *
     * POST /api/admin/barcode/preview
     */
    public function preview(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string', 'max:128'],
            'format' => ['required', Rule::in(['code128', 'ean13', 'qr'])],
        ]);

        try {
            $svg = $this->barcodeService->generateSvg($validated['value'], $validated['format']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['svg' => $svg]);
    }
}
