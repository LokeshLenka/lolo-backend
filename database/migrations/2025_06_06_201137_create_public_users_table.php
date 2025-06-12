<?php

use App\Enums\GenderType;
use App\Enums\AcademicYear;
use App\Enums\BranchType;
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
        Schema::create('public_users', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('reg_num', 10)->unique();
            $table->string('name', 30);
            $table->enum('gender', GenderType::values());
            $table->enum('year', AcademicYear::values());
            $table->enum('branch', BranchType::values());
            $table->string('phone_no', 15)->unique()->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_users');
    }
};
