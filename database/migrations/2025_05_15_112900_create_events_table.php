<?php

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\RegistrationMode;
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
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('coordinator1')->nullable()->constrained('users');
            $table->foreignId('coordinator2')->nullable()->constrained('users');
            $table->foreignId('coordinator3')->nullable()->constrained('users');

            $table->string('name', 100)->unique();
            $table->text('description');
            $table->enum('type', EventType::values());
            $table->dateTimeTz('start_date');
            $table->dateTimeTz('end_date');
            $table->text('venue');
            $table->enum('status', EventStatus::values());
            $table->decimal('credits_awarded', 4, 2); //changes form int to float
            $table->decimal('fee')->default(0);
            $table->dateTimeTz('registration_deadline');
            $table->integer('max_participants')->nullable();
            $table->enum('registration_mode', RegistrationMode::values());
            $table->string('registration_place', 150)->nullable();


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
        // Schema::dropIfExists('credits');
        // Schema::dropIfExists('event_registrations');

        Schema::dropIfExists('events');
    }
};
