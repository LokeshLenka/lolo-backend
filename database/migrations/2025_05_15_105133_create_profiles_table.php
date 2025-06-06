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
        Schema::create('management_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('reg_num', 10)->unique()->index();
            $table->string('branch', 4);
            $table->enum('year', ['1', '2', '3', '4', 'passedout']);
            $table->integer('phone_no');
            $table->enum('gender', ['male', 'female']);

            $table->enum(
                'category_of_interest',
                [
                    'event_organizer',
                    'event_planner',
                    'marketing',
                    'social_media_handler',
                    'video_editor',
                    'credit_manager',
                    'ebm',
                ]
            );

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
