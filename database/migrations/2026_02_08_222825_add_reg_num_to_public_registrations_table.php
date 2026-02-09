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
            $table->string('reg_num')->after('public_user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('public_registration', 'reg_num')) {
                $table->dropColumn('reg_num');
            }
        });
    }
};
