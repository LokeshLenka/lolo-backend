<?php

use App\Enums\UserApprovalStatus;
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
        Schema::create('user_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')           // The user being approved
                ->constrained('users')
                ->onDelete('cascade');

            // $table->foreignId('requested_by')      // EBM who created the user
            //       ->nullable()
            //       ->constrained('users')
            //       ->onDelete('set null');

            $table->foreignId('approved_by')       // MCH who approved or rejected
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->enum('status', UserApprovalStatus::values())
                ->default('pending');

            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_approvals');
    }
};
