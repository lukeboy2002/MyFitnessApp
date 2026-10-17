<?php

use App\Models\CardioLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tussentijden per km (loopband): hoe lang je over elke gelopen km deed.
     */
    public function up(): void
    {
        Schema::create('cardio_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CardioLog::class)->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');              // km 1, 2, 3 ...
            $table->unsignedInteger('duration_seconds');         // tijd voor deze km
            $table->timestamps();

            $table->unique(['cardio_log_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cardio_splits');
    }
};
