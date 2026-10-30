<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admin: wanneer de gebruikerslijst voor het laatst is bekeken (voor het aantal nieuwe accounts)
            $table->timestamp('users_seen_at')->nullable()->after('is_admin');
        });

        // Bestaande admins kennen de huidige accounts al: hun teller begint op 0
        DB::table('users')->where('is_admin', true)->update(['users_seen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('users_seen_at');
        });
    }
};
