<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_design_templates')->where('key', 'bottle-wrap-v1')->where('version', 1)->update(['version' => 2, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('product_design_templates')->where('key', 'bottle-wrap-v1')->where('version', 2)->update(['version' => 1, 'updated_at' => now()]);
    }
};
