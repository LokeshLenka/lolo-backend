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
            $table->string('qr_code_path')->nullable()->after('registration_place');

            // Drop old columns
            $table->dropColumn(['qr_code', 'qr_code_mime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->mediumBlob('qr_code')->nullable();
            $table->string('qr_code_mime')->nullable();
            $table->dropColumn('qr_code_path');
        });
    }
};
