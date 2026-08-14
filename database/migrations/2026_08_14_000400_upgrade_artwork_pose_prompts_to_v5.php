<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('artwork_styles')->whereIn('slug', ['storybook-cartoon', 'hand-drawn'])->update(['prompt_version' => 5]);
    }

    public function down(): void
    {
        DB::table('artwork_styles')->whereIn('slug', ['storybook-cartoon', 'hand-drawn'])->where('prompt_version', 5)->update(['prompt_version' => 4]);
    }
};
