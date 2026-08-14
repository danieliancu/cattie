<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('composed_designs', function (Blueprint $table) {
            $table->string('storage_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('composed_designs', function (Blueprint $table) {
            $table->string('storage_key')->nullable(false)->change();
        });
    }
};
