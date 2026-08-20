<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('recovery_first_sent_at')->nullable()->after('placed_at');
            $table->timestamp('recovery_second_sent_at')->nullable()->after('recovery_first_sent_at');
            $table->timestamp('recovery_unsubscribed_at')->nullable()->after('recovery_second_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['recovery_first_sent_at', 'recovery_second_sent_at', 'recovery_unsubscribed_at']);
        });
    }
};
