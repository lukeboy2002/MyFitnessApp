<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Opwarmset: telt niet mee voor records, 1RM, voortgang en het voorstel voor de volgende keer */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('set_logs', function (Blueprint $table) {
            $table->boolean('is_warmup')->default(false)->after('set_number');
        });
    }

    public function down(): void
    {
        Schema::table('set_logs', function (Blueprint $table) {
            $table->dropColumn('is_warmup');
        });
    }
};
