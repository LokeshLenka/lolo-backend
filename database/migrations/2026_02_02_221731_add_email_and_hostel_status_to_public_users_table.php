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
        Schema::table('public_users', function (Blueprint $table) {
            if (!Schema::hasColumn('public_users', 'email')) {
                $table->string('email')->unique()->after('reg_num');
            }
            if (!Schema::hasColumn('public_users', 'college_hostel_status')) {
                $table->boolean('college_hostel_status')->default(false)->after('phone_no');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_users', function (Blueprint $table) {
            if (Schema::hasColumn('public_users', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('public_users', 'college_hostel_status')) {
                $table->dropColumn('college_hostel_status');
            }
        });
    }
};
