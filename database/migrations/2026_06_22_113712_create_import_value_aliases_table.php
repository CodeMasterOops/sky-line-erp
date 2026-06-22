<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_value_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 64);
            $table->string('field', 64);
            $table->string('source_value');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_value')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'entity_type', 'field', 'source_value'], 'import_value_aliases_unique');
            $table->index(['company_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_value_aliases');
    }
};
