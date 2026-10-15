<?php

use App\Models\BodyPart;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muscle_groups', function (Blueprint $table) {
            $table->id();
            // Lichaamsdeel met spiergroepen kan niet verwijderd worden
            $table->foreignIdFor(BodyPart::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 7)->default('#1d63c5'); // hex, voor badges en chips
            $table->string('icon')->nullable();             // naam uit <x-icon>
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muscle_groups');
    }
};
