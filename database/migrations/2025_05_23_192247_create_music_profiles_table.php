<?php

use App\Enums\AcademicYear;
use App\Enums\BranchType;
use App\Enums\GenderType;
use App\Enums\MusicCategories;
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
        Schema::create('music_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('reg_num', 10)->unique();
            $table->enum('branch', BranchType::values());
            $table->enum('year', AcademicYear::values());
            $table->string('phone_no', 15)->unique();
            $table->enum('gender', GenderType::values());

            $table->enum('sub_role', MusicCategories::values());

            $table->boolean('instrument_avail')->default(false);
            $table->text('other_fields_of_interest');
            $table->text('experience');
            $table->text('passion');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
        Schema::dropIfExists('music_profiles');
    }
};
