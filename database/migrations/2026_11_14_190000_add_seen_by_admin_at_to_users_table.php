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
            // Wanneer een admin dit account in de gebruikerslijst heeft aangeklikt; leeg = nieuw (melding in het menu)
            $table->timestamp('seen_by_admin_at')->nullable()->after('is_admin');
        });

        // Bestaande accounts zijn al bekend: geen stapel meldingen
        DB::table('users')->update(['seen_by_admin_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('seen_by_admin_at');
        });
    }
};
