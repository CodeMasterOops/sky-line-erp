<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->timestamp('dispatched_at')->nullable()->after('approved_at');
            $table->timestamp('received_at')->nullable()->after('dispatched_at');
            $table->unsignedBigInteger('dispatch_user_id')->nullable()->after('received_at');
            $table->unsignedBigInteger('receive_user_id')->nullable()->after('dispatch_user_id');
            $table->foreign('dispatch_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('receive_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['dispatch_user_id']);
            $table->dropForeign(['receive_user_id']);
            $table->dropColumn(['dispatched_at', 'received_at', 'dispatch_user_id', 'receive_user_id']);
        });
    }
};
