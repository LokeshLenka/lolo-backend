<?php

use App\Enums\AcademicYear;
use App\Enums\BranchType;
use App\Enums\GenderType;
use App\Enums\ManagementCategories;
use App\Enums\PromotedRole;
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
        Schema::create('management_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('reg_num', 10)->unique()->index();
            $table->enum('branch', BranchType::values());
            $table->enum('year', AcademicYear::values());
            $table->string('phone_no', 15)->unique();
            $table->enum('gender', GenderType::values());

            $table->enum('sub_role', ManagementCategories::values());

            $table->text('experience')->nullable();
            $table->text('interest_towards_lolo')->nullable();
            $table->string('any_club')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('management_profiles');
    }
};
