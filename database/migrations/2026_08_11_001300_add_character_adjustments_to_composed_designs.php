<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('composed_designs', fn (Blueprint $table) => $table->json('character_adjustments')->nullable()->after('personalisation_snapshot'));
    }

    public function down(): void
    {
        Schema::table('composed_designs', fn (Blueprint $table) => $table->dropColumn('character_adjustments'));
    }
};
