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
            // nl, en of de (config/app.php locales). Leeg = taal van de browser
            $table->string('locale', 5)->nullable()->after('resting_heart_rate');
        });

        // Bestaande accounts houden de app in het Nederlands, ook als hun browser Engels is
        DB::table('users')->update(['locale' => 'nl']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
