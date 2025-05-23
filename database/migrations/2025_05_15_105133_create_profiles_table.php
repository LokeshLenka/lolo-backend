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
            $table->foreignId('user_id')->constrained('users');

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
                    'video_editor'
                ]
            );

            $table->text('experience');
            $table->text('interest_towards_lolo');
            $table->string('any_club');

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
        Schema::dropIfExists('profiles');
    }
};
