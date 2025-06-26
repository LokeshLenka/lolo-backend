<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_approvals', function (Blueprint $table) {
            // Drop old single approval column if exists
            if (Schema::hasColumn('user_approvals', 'approved_by')) {
                $table->dropForeign(['approved_by']);
                $table->dropColumn('approved_by');
            }

            // Add workflow fields
            $table->foreignId('assigned_ebm_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('ebm_assigned_at')->nullable()->after('assigned_ebm_id');
            $table->timestamp('ebm_approved_at')->nullable()->after('ebm_assigned_at');

            $table->foreignId('assigned_membership_head_id')
                ->nullable()
                ->after('ebm_approved_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('membership_head_assigned_at')->nullable()->after('assigned_membership_head_id');
            $table->timestamp('membership_head_approved_at')->nullable()->after('membership_head_assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_approvals', function (Blueprint $table) {
            // Drop new foreign keys and columns
            $table->dropForeign(['assigned_ebm_id']);
            $table->dropForeign(['assigned_membership_head_id']);

            $table->dropColumn([
                'assigned_ebm_id',
                'ebm_assigned_at',
                'ebm_approved_at',
                'assigned_membership_head_id',
                'membership_head_assigned_at',
                'membership_head_approved_at',
            ]);

            // Restore old approval column
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }
};
