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
        Schema::table('users', function (Blueprint $table) {
            // Add column only if it doesn't exist
            if (!Schema::hasColumn('users', 'promoted_by')) {
                $table
                    ->foreignId('promoted_by')
                    ->nullable()
                    ->after('promoted_role')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'promoted_by')) {
                // Drop FK first, then column
                $table->dropForeign(['promoted_by']);
                $table->dropColumn('promoted_by');
            }
        });
    }
};
