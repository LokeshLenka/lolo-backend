<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('teams', 'team_profile');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('team_profile', 'teams');
    }
};
