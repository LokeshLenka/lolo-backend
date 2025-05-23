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
        Schema::create('team_images', function (Blueprint $table) {

            $table->unsignedBigInteger('team_member_id');
            $table->foreign('team_member_id')->references('id')->on('teams')->cascadeOnDelete()->cascadeOnUpdate();

            $table->unsignedBigInteger('image_id');
            $table->foreign('image_id')->references('id')->on('images')->cascadeOnDelete();

            $table->primary(['team_member_id', 'image_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_images');
    }
};
