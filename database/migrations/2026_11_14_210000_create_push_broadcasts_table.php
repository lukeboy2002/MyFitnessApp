<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pushmeldingen die de admin zelf verstuurt (Admin > Pushmeldingen), met geschiedenis */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete(); // afzender (admin)
            $table->foreignIdFor(User::class, 'recipient_id')->nullable()->constrained('users')->nullOnDelete(); // leeg bij iedereen
            $table->boolean('to_everyone')->default(true);
            $table->string('recipient_name')->nullable(); // blijft zichtbaar als het account later weg is
            $table->string('title', 80);
            $table->string('body', 300);
            $table->string('url')->nullable();
            $table->unsignedInteger('users_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_broadcasts');
    }
};
