<?php

use App\Enums\IsPaid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Enum;
use App\Enums\RegistrationStatus;
use App\Enums\PaymentStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('uuid');

            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('event_id')->constrained('events');
            $table->unique(['user_id', 'event_id']);

            $table->timestamp('registered_at')->useCurrent();
            $table->enum('is_paid', IsPaid::values());
            $table->enum('registration_status', RegistrationStatus::values());
            $table->string('ticket_code')->unique()->nullable();

            $table->enum('payment_status', PaymentStatus::values());
            $table->string('payment_reference');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
