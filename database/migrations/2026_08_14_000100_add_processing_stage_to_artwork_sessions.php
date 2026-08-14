<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artwork_sessions', function (Blueprint $table) {
            $table->string('processing_stage')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('artwork_sessions', function (Blueprint $table) {
            $table->dropColumn('processing_stage');
        });
    }
};
