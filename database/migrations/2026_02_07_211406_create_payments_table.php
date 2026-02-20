<?php

use App\Enums\PayableType;
use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Who paid (logical reference)
            $table->enum('payer_type', PayerType::values());
            $table->unsignedBigInteger('payer_id')->nullable(); // stores public_user_id or internal user id

            // Snapshot (never changes)
            $table->string('payer_name');
            $table->string('payer_identifier'); // stores reg num

            // What is being paid for
            $table->enum('payable_type', PayableType::values());
            $table->unsignedBigInteger('payable_id');

            // Razorpay details
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->string('razorpay_signature')->nullable();

            // Payment info
            $table->unsignedInteger('amount'); // paise
            $table->enum('payment_method', PaymentMethod::values())->nullable();

            $table->enum('status', PaymentStatus::values())
                ->default(PaymentStatus::PENDING->value);

            // Verification & audit
            $table->boolean('is_verified')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->json('gateway_response')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['payer_type', 'payer_id']);
            $table->index(['payable_type', 'payable_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
