<?php

namespace App\Console\Commands;

use App\Enums\PromotedRole;
use App\Enums\UserApprovalStatus;
use App\Models\User;
use App\Models\UserApproval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AssignReviewers extends Command
{
    protected $signature = 'approvals:assign-reviewers';
    protected $description = 'Automatically assigns EBM and Membership Heads to users needing approval.';

    public function handle()
    {
        DB::beginTransaction();

        try {

            $pendingApprovals = UserApproval::where(function ($q) {
                $q->whereNull('assigned_ebm_id')
                    ->orWhereNull('assigned_membership_head_id');
            })
                ->where('status', UserApprovalStatus::PENDING->value) // ✅ Only keep PENDING status
                ->with('user')
                ->get();

            $activeEbms = $this->getEligibleReviewers(PromotedRole::EXECUTIVE_BODY_MEMBER);
            $activeMembershipHeads = $this->getEligibleReviewers(PromotedRole::MEMBERSHIP_HEAD);

            foreach ($pendingApprovals as $approval) {
                $user = $approval->user;

                if (!$user || !$user->is_active) continue;

                // Assign EBM
                if (!$approval->assigned_ebm_id) {
                    $ebm = $this->selectReviewer($activeEbms, $user->gender, 'ebm');
                    if ($ebm) {
                        $approval->assigned_ebm_id = $ebm->id;
                        $approval->ebm_assigned_at = now();
                        Log::info("Assigned EBM [{$ebm->id}] to user [{$user->id}]");
                    }
                }

                // Assign Membership Head
                if (!$approval->assigned_membership_head_id) {
                    $mh = $this->selectReviewer($activeMembershipHeads, $user->gender, 'membership_head');
                    if ($mh) {
                        $approval->assigned_membership_head_id = $mh->id;
                        $approval->membership_head_assigned_at = now();
                        Log::info("Assigned Membership Head [{$mh->id}] to user [{$user->id}]");
                    }
                }

                $approval->save();
            }

            DB::commit();
            $this->info('Reviewer assignment completed successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Reviewer assignment failed: ' . $e->getMessage());
            $this->error('Assignment failed. Check logs.');
        }
    }

    /**
     * Get active users who have the given promoted role.
     */
    private function getEligibleReviewers(PromotedRole $role)
    {
        return User::where('is_active', true)
            ->where('promoted_role', $role->value)
            ->get();
    }

    /**
     * Selects a reviewer with same gender preference and minimal assignments in last 30 days.
     */
    private function selectReviewer($reviewers, $targetGender, string $type)
    {
        if ($reviewers->isEmpty()) return null;

        $sameGender = $reviewers->where('gender', $targetGender);
        $fallback = $sameGender->isNotEmpty() ? $sameGender : $reviewers;

        $from = Carbon::now()->subDays(30);

        return $fallback->sortBy(function ($reviewer) use ($type, $from) {
            return UserApproval::where("assigned_{$type}_id", $reviewer->id)
                ->where("{$type}_assigned_at", '>=', $from)
                ->count();
        })->first();
    }
}
