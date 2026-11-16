<?php

use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Contact: vragen van gebruikers aan de admin, met de berichten heen en weer */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('topic', 20);
            $table->string('subject', 120);
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('user_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ContactRequest::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_admin')->default(false);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_replies');
        Schema::dropIfExists('contact_requests');
    }
};
