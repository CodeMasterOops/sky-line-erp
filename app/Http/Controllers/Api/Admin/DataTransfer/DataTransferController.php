<?php

namespace App\Http\Controllers\Api\Admin\DataTransfer;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\DataTransferJob;
use App\Services\TenantService;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\DataTransferSchedule;
use App\Jobs\DataTransfer\ParseFileJob;
use Illuminate\Support\Facades\Storage;
use App\Jobs\DataTransfer\ValidateFileJob;
use Illuminate\Support\Facades\RateLimiter;
use App\Jobs\DataTransfer\GenerateExportJob;
use App\Jobs\DataTransfer\RollbackImportJob;
use App\Services\DataTransfer\UploadService;
use App\Jobs\DataTransfer\ProcessImportChunkJob;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Services\DataTransfer\ImportTemplateService;
use App\Enums\DataTransfer\DataTransferDirectionEnum;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;
use App\Http\Resources\Admin\DataTransfer\DataTransferJobResource;
use App\Http\Resources\Admin\DataTransfer\DataTransferRowResource;

class DataTransferController extends Controller
{
    public function __construct(
        private UploadService $uploadService,
        private ImportTemplateService $templateService,
    ) {}

    /**
     * @Permissions("list_data_transfer", group="data_transfer", desc="List Data Transfer Jobs")
     */
    public function index(Request $request): JsonResponse
    {
        $jobs = DataTransferJob::query()
            ->where('company_id', auth('admin')->user()->company_id)
            ->when($request->direction, fn ($q, $d) => $q->where('direction', $d))
            ->when($request->entity_type, fn ($q, $t) => $q->where('entity_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(min(max((int) $request->get('limit', 25), 1), 100));

        return DataTransferJobResource::collection($jobs)->response();
    }

    /**
     * @Permissions("list_data_transfer", group="data_transfer", desc="List Data Transfer Jobs")
     */
    public function show(string $uuid): JsonResponse
    {
        $job = $this->findCompanyJob($uuid);

        return response()->json([
            'data' => DataTransferJobResource::make($job),
        ]);
    }

    /**
     * @Permissions("list_data_transfer", group="data_transfer", desc="List Data Transfer Jobs")
     */
    public function previewRows(string $uuid, Request $request): JsonResponse
    {
        $job = $this->findCompanyJob($uuid);
        $status = $request->get('status', 'invalid');
        $limit = min(max((int) $request->get('limit', 50), 1), 100);

        $rows = $job->rows()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('row_number')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => DataTransferRowResource::collection($rows),
        ]);
    }

    /**
     * @Permissions("import_product", group="data_transfer", desc="Import Products")
     */
    public function storeImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.((int) config('data_transfer.max_upload_bytes', 20971520) / 1024)],
            'entity_type' => ['required', Rule::enum(DataTransferEntityTypeEnum::class)],
        ]);

        $user = auth('admin')->user();
        $key = 'data-transfer-upload:'.$user->company_id;

        if (RateLimiter::tooManyAttempts($key, (int) config('data_transfer.uploads_per_hour', 10))) {
            return response()->json(['message' => 'Upload limit exceeded. Try again later.'], 429);
        }

        RateLimiter::hit($key, 3600);

        $entityType = DataTransferEntityTypeEnum::from($request->input('entity_type'));

        if ($entityType !== DataTransferEntityTypeEnum::Product) {
            return response()->json(['message' => 'Only product import is enabled.'], 422);
        }

        $job = $this->uploadService->storeImport(
            $request->file('file'),
            $user,
            $entityType,
            TenantService::branchId(),
        );

        ParseFileJob::dispatch($job);

        return response()->json([
            'data' => DataTransferJobResource::make($job),
            'message' => 'File uploaded. Parsing started.',
        ], 201);
    }

    /**
     * @Permissions("import_product", group="data_transfer", desc="Import Products")
     */
    public function updateMapping(string $uuid, Request $request): JsonResponse
    {
        $job = $this->findCompanyJob($uuid);

        if ($job->direction !== DataTransferDirectionEnum::Import) {
            return response()->json(['message' => 'Not an import job.'], 422);
        }

        $validated = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['required', 'string'],
            'duplicate_mode' => ['nullable', Rule::in(['skip', 'update', 'create_only'])],
        ]);

        $options = $job->options ?? [];
        if (isset($validated['duplicate_mode'])) {
            $options['duplicate_mode'] = $validated['duplicate_mode'];
        }

        $job->update([
            'mapping' => $validated['mapping'],
            'options' => $options,
            'status' => DataTransferStatusEnum::Mapped,
        ]);

        return response()->json([
            'data' => DataTransferJobResource::make($job->fresh()),
            'message' => 'Mapping saved.',
        ]);
    }

    /**
     * @Permissions("import_product", group="data_transfer", desc="Import Products")
     */
    public function validateJob(string $uuid): JsonResponse
    {
        $job = $this->findCompanyJob($uuid);

        if (! in_array($job->status, [
            DataTransferStatusEnum::Mapped,
            DataTransferStatusEnum::Parsed,
            DataTransferStatusEnum::Validated,
        ], false)) {
            return response()->json(['message' => 'Job is not ready for validation.'], 422);
        }

        ValidateFileJob::dispatch($job);

        return response()->json([
            'message' => 'Validation started.',
        ]);
    }

    /**
     * @Permissions("import_product", group="data_transfer", desc="Import Products")
     */
    public function commit(string $uuid): JsonResponse
    {
        $job = $this->findCompanyJob($uuid);

        if ($job->status !== DataTransferStatusEnum::Validated) {
            return response()->json(['message' => 'Validate the file before importing.'], 422);
        }

        $valid = (int) ($job->stats['valid'] ?? 0);
        if ($valid === 0) {
            return response()->json(['message' => 'No valid rows to import.'], 422);
        }

        ProcessImportChunkJob::dispatch($job, 0);

        return response()->json([
            'message' => 'Import started.',
        ]);
    }

    /**
     * @Permissions("import_product", group="data_transfer", desc="Import Products")
     */
    public function cancel(string $uuid): JsonResponse
    {
        $job = $this->findCompanyJob($uuid);

        if (in_array($job->status, [
            DataTransferStatusEnum::Completed,
            DataTransferStatusEnum::CompletedWithErrors,
            DataTransferStatusEnum::Failed,
            DataTransferStatusEnum::RolledBack,
        ], false)) {
            return response()->json(['message' => 'Job already finished.'], 422);
        }

        $job->update(['status' => DataTransferStatusEnum::Cancelled]);

        return response()->json(['message' => 'Job cancelled.']);
    }

    /**
     * @Permissions("import_product", group="data_transfer", desc="Import Products")
     */
    public function rollback(string $uuid): JsonResponse
    {
        $job = $this->findCompanyJob($uuid);

        if ($job->direction !== DataTransferDirectionEnum::Import) {
            return response()->json(['message' => 'Not an import job.'], 422);
        }

        RollbackImportJob::dispatch($job);

        return response()->json(['message' => 'Rollback started.']);
    }

    /**
     * @Permissions("export_data", group="data_transfer", desc="Export Business Data")
     */
    public function storeExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', Rule::enum(DataTransferEntityTypeEnum::class)],
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
            'filters' => ['nullable', 'array'],
        ]);

        $job = $this->uploadService->createExportJob(
            auth('admin')->user(),
            DataTransferEntityTypeEnum::from($validated['entity_type']),
            $validated['format'],
            $validated['filters'] ?? [],
            TenantService::branchId(),
        );

        GenerateExportJob::dispatch($job);

        return response()->json([
            'data' => DataTransferJobResource::make($job),
            'message' => 'Export queued.',
        ], 201);
    }

    /**
     * @Permissions("list_data_transfer", group="data_transfer", desc="List Data Transfer Jobs")
     */
    public function download(string $uuid)
    {
        $job = $this->findCompanyJob($uuid);

        if (! $job->result_disk || ! $job->result_path) {
            return response()->json(['message' => 'File not available.'], 404);
        }

        $filename = ($job->original_filename ?? $job->entity_type->value.'-export')
            .'.'.pathinfo($job->result_path, PATHINFO_EXTENSION);

        return Storage::disk($job->result_disk)->download($job->result_path, $filename);
    }

    /**
     * @Permissions("list_data_transfer", group="data_transfer", desc="List Data Transfer Jobs")
     */
    public function downloadErrors(string $uuid)
    {
        $job = $this->findCompanyJob($uuid);

        if (! $job->result_disk || ! $job->result_path) {
            return response()->json(['message' => 'Error report not available.'], 404);
        }

        return Storage::disk($job->result_disk)->download(
            $job->result_path,
            'import-errors-'.$job->uuid.'.csv'
        );
    }

    /**
     * @Permissions("import_product", group="data_transfer", desc="Import Products")
     */
    public function productTemplate(Request $request)
    {
        $format = $request->get('format', 'csv');

        return $this->templateService->downloadProductTemplate($format === 'xlsx' ? 'xlsx' : 'csv');
    }

    /**
     * @Permissions("export_data", group="data_transfer", desc="Export Business Data")
     */
    public function schedules(Request $request): JsonResponse
    {
        $schedules = DataTransferSchedule::query()
            ->where('company_id', auth('admin')->user()->company_id)
            ->latest()
            ->paginate(25);

        return response()->json(['data' => $schedules]);
    }

    /**
     * @Permissions("export_data", group="data_transfer", desc="Export Business Data")
     */
    public function storeSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', Rule::enum(DataTransferEntityTypeEnum::class)],
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
            'cron_expression' => ['required', 'string', 'max:64'],
            'filters' => ['nullable', 'array'],
            'recipients' => ['nullable', 'array'],
        ]);

        $schedule = DataTransferSchedule::query()->create([
            'company_id' => auth('admin')->user()->company_id,
            'user_id' => auth('admin')->id(),
            ...$validated,
        ]);

        return response()->json([
            'data' => $schedule,
            'message' => 'Schedule created.',
        ], 201);
    }

    private function findCompanyJob(string $uuid): DataTransferJob
    {
        return DataTransferJob::query()
            ->where('uuid', $uuid)
            ->where('company_id', auth('admin')->user()->company_id)
            ->firstOrFail();
    }
}
