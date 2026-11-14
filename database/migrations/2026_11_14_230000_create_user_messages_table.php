<?php

use App\Models\PushBroadcast;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Berichten van de admin per gebruiker (pagina Berichten): gelezen en verwijderen per gebruiker */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(PushBroadcast::class)->nullable()->constrained()->nullOnDelete();
            $table->string('title', 80);
            $table->string('body', 300);
            $table->string('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_messages');
    }
};
