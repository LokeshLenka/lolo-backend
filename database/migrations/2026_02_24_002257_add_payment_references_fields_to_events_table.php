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
        Schema::table('events', function (Blueprint $table) {
            $table->binary('qr_code')->nullable()->after('uuid');
            $table->string('qr_code_mime')->nullable()->after('qr_code');
            $table->string('payment_link', 64)->nullable()->after('qr_code_mime');
        });
    }


    /**
     * Reverse the migrations.
     */

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['qr_code', 'qr_code_mime', 'payment_link']);
        });
    }
};
