<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email');

            // Role selection
            $table->string('role'); // Student, Musician, Faculty, Sponsor, Others
            $table->string('custom_role')->nullable(); // only if role = Others

            $table->text('message');

            // Admin handling
            $table->boolean('is_read')->default(false);
            $table->timestamp('replied_at')->nullable();

            $table->timestamps();

            // Optional but smart indexing
            $table->index('email');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
