<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('ip_address');
            $table->boolean('successful')->default(false);
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Store additional security info
            $table->timestamps();

            $table->index(['email', 'ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
