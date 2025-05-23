<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('coordinator1')->nullable()->constrained('users');
            $table->foreignId('coordinator2')->nullable()->constrained('users');
            $table->foreignId('coordinator3')->nullable()->constrained('users');

            $table->string('name', 100);
            $table->string('description');
            $table->enum('type', ['all', 'club', 'members']);
            $table->dateTimeTz('timings');
            $table->string('venue');
            $table->enum('status', ['upcoming', 'ongoing', 'completed']);
            $table->float('credits_awarded'); //changes form int to float
            $table->dateTimeTz('registration_deadline');
            $table->integer('max_participants')->nullable();
            $table->enum('registration_mode',['online','offline']);
            $table->string('registration_place',150)->nullable();

            // $table->enum('category',['jam','openmic','etc']); skips for now

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
