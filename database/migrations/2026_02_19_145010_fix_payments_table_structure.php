<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            // 1️⃣ Make payer_id NOT NULL
            $table->unsignedBigInteger('payer_id')->nullable(false)->change();

            // 2️⃣ Make access_token NOT NULL + UNIQUE
            $table->string('access_token', 64)
                ->nullable(false)
                ->unique()
                ->change();

            // 3️⃣ Add index for faster lookups
            $table->index('access_token');
        });

        // 4️⃣ Remove is_verified column (status will handle final state)
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'is_verified')) {
                $table->dropColumn('is_verified');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            // revert payer_id
            if (Schema::hasColumn('payments', 'payer_id')) {
                $table->unsignedBigInteger('payer_id')->nullable()->change();
            }
            // revert access_token

            if (Schema::hasColumn('payments', 'access_token')) {
                $table->string('access_token')->nullable()->change();
                $table->dropUnique(['access_token']);
                $table->dropIndex(['access_token']);
            }
        });

        // restore is_verified if rolled back
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false);
        });
    }
};
