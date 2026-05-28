<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_transfer_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('direction', 16);
            $table->string('entity_type', 64);
            $table->string('status', 32)->default('uploaded');
            $table->string('file_disk', 32)->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->json('options')->nullable();
            $table->json('mapping')->nullable();
            $table->json('stats')->nullable();
            $table->text('error_summary')->nullable();
            $table->string('result_disk', 32)->nullable();
            $table->string('result_path')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['company_id', 'entity_type', 'direction']);
        });

        Schema::create('data_transfer_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_transfer_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status', 32);
            $table->json('raw_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->json('errors')->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->unique(['data_transfer_job_id', 'row_number']);
            $table->index(['data_transfer_job_id', 'status']);
        });

        Schema::create('data_transfer_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('entity_type', 64);
            $table->json('mapping');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_transfer_mappings');
        Schema::dropIfExists('data_transfer_rows');
        Schema::dropIfExists('data_transfer_jobs');
    }
};
