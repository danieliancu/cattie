<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('composed_designs', fn (Blueprint $table) => $table->string('editor_background_storage_key')->nullable()->unique()->after('preview_storage_key'));
    }

    public function down(): void
    {
        Schema::table('composed_designs', fn (Blueprint $table) => $table->dropColumn('editor_background_storage_key'));
    }
};
