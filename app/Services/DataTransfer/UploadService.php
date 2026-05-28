<?php

namespace App\Services\DataTransfer;

use App\Models\User;
use Illuminate\Support\Str;
use App\Models\DataTransferJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Enums\DataTransfer\DataTransferDirectionEnum;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;

class UploadService
{
    public function storeImport(
        UploadedFile $file,
        User $user,
        DataTransferEntityTypeEnum $entityType,
        ?int $branchId = null,
    ): DataTransferJob {
        $disk = config('data_transfer.disk', 'local');
        $maxBytes = (int) config('data_transfer.max_upload_bytes', 20 * 1024 * 1024);

        if ($file->getSize() > $maxBytes) {
            throw new \InvalidArgumentException('File exceeds maximum upload size.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, config('data_transfer.allowed_extensions', ['csv', 'xlsx']), true)) {
            throw new \InvalidArgumentException('Unsupported file extension.');
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $path = sprintf(
            'data-transfer/imports/%d/%s.%s',
            $user->company_id,
            Str::uuid(),
            $extension
        );

        Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return DataTransferJob::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'direction' => DataTransferDirectionEnum::Import,
            'entity_type' => $entityType,
            'status' => DataTransferStatusEnum::Uploaded,
            'file_disk' => $disk,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'options' => [
                'duplicate_mode' => 'update',
            ],
        ]);
    }

    public function createExportJob(
        User $user,
        DataTransferEntityTypeEnum $entityType,
        string $format,
        array $filters = [],
        ?int $branchId = null,
    ): DataTransferJob {
        return DataTransferJob::query()->create([
            'company_id' => $user->company_id,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'direction' => DataTransferDirectionEnum::Export,
            'entity_type' => $entityType,
            'status' => DataTransferStatusEnum::Uploaded,
            'options' => [
                'format' => $format,
                'filters' => $filters,
            ],
        ]);
    }
}
