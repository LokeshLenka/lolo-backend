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
        Schema::table('public_registration', function (Blueprint $table) {
            $table->renameColumn('public_user_id', 'reg_num');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_registration', function (Blueprint $table) {
            $table->renameColumn('reg_num', 'public_user_id');
        });
    }
};
