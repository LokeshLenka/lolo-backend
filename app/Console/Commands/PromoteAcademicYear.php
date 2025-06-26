<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\MusicProfile;
use App\Models\ManagementProfile;
use App\Enums\AcademicYear;
use Illuminate\Support\Facades\DB;

class PromoteAcademicYear extends Command
{
    protected $signature = 'profiles:promote-academic-year';
    protected $description = 'Promote academic year in music and management profiles, and deactivate passed-out users';

    public function handle()
    {
        DB::transaction(function () {
            $this->promoteProfile(MusicProfile::class);
            $this->promoteProfile(ManagementProfile::class);
        });

        $this->info('Academic year promotion completed successfully.');
    }

    private function promoteProfile(string $profileModel)
    {
        $profileModel::with('user') // assuming each profile has a `user()` relationship
            ->whereIn('year', [
                AcademicYear::First->value,
                AcademicYear::Second->value,
                AcademicYear::Third->value,
                AcademicYear::Fourth->value
            ])
            ->chunkById(100, function ($profiles) {
                foreach ($profiles as $profile) {
                    $nextYear = $this->getNextAcademicYear($profile->year);

                    $profile->year = $nextYear;
                    $profile->save();

                    if ($nextYear === AcademicYear::PassedOut->value && $profile->user) {
                        $profile->user->is_active = false;
                        $profile->user->save();
                    }
                }
            });
    }

    private function getNextAcademicYear(AcademicYear $current): string
    {
        return match ($current) {
            AcademicYear::First => AcademicYear::Second->value,
            AcademicYear::Second => AcademicYear::Third->value,
            AcademicYear::Third => AcademicYear::Fourth->value,
            AcademicYear::Fourth => AcademicYear::PassedOut->value,
            default => AcademicYear::PassedOut->value,
        };
    }
}
