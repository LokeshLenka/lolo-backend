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
        Schema::table('public_registrations', function (Blueprint $table) {
            $table->string('utr', 20)->unique()->nullable()->after('ticket_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_registrations', function (Blueprint $table) {
            $table->dropColumn('utr');
        });
    }
};
