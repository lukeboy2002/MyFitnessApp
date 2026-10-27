<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('chest_cm', 4, 1)->nullable();
            $table->decimal('waist_cm', 4, 1)->nullable();
            $table->decimal('hips_cm', 4, 1)->nullable();
            $table->decimal('arm_cm', 4, 1)->nullable();
            $table->decimal('thigh_cm', 4, 1)->nullable();
            $table->decimal('calf_cm', 4, 1)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'measured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_measurements');
    }
};
