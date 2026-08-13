<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('artwork_styles')->where('slug', 'storybook-cartoon')->update(['prompt_version' => 4]);
    }

    public function down(): void
    {
        DB::table('artwork_styles')->where('slug', 'storybook-cartoon')->where('prompt_version', 4)->update(['prompt_version' => 3]);
    }
};
