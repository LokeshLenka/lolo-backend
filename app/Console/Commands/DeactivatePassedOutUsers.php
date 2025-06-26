<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enums\AcademicYear;
use App\Models\MusicProfile;
use App\Models\ManagementProfile;

class DeactivatePassedOutUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deactivate-passed-out-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate users who have passed out based on their academic year';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            DB::transaction(function () {
                $this->deactivateFromProfile(MusicProfile::class);
                $this->deactivateFromProfile(ManagementProfile::class);
            });

            $this->info('All passed-out users have been deactivated successfully.');
        } catch (\Throwable $e) {
            Log::error("Failed to deactivate passed-out users: {$e->getMessage()}");
            $this->error('Deactivation failed. Check logs for details.');
        }
    }

    private function deactivateFromProfile(string $profileModel)
    {
        $profiles = $profileModel::with('user')
            ->where('academic_year', AcademicYear::PassedOut->value)
            ->get();

        foreach ($profiles as $profile) {
            if ($profile->user && $profile->user->is_active) {
                $profile->user->is_active = false;
                $profile->user->save();

                Log::info("User [{$profile->user->id}] marked as inactive due to passing out.");
            }
        }
    }
}
