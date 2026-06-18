<?php

use Illuminate\Database\Migrations\Migration;

// StatusEnum is a PHP-native enum — IN_TRANSIT is added directly to the enum class.
// The status column stores plain strings, so no schema change is required.
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
