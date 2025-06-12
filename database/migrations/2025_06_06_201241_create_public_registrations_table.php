<?php

use App\Enums\IsPaid;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
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
        Schema::create('public_registration', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->autoIncrement();

            $table->foreignId('public_user_id')->constrained('public_users');
            $table->foreignId('event_id')->constrained('events');

            $table->string('ticket_code')->unique()->nullable();
            // $table->text('registered_users')->
            $table->enum('is_paid', IsPaid::values());
            $table->enum('payment_status', PaymentStatus::values());
            $table->enum('registration_status', RegistrationStatus::values());

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_registrations');
    }
};
