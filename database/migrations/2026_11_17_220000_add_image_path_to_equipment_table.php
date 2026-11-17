<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Plaatje bij materiaal (net als bij spiergroepen); standaardmateriaal kan ook storage/app/public/equipment/{slug}.png gebruiken */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
