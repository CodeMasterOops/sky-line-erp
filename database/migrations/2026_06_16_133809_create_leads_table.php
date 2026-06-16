<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('pan')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('business_type');
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->string('plan_interest')->nullable();
            $table->unsignedSmallInteger('branch_count')->default(1);
            $table->text('note')->nullable();
            $table->string('status')->default('new');
            $table->text('follow_up_note')->nullable();
            $table->timestamp('followed_up_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
