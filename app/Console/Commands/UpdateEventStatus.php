<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Console\Command;

class UpdateEventStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update events from upcoming → ongoing, and ongoing → completed based on time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $updatedOngoing = Event::where('status', EventStatus::UPCOMING->value)
            ->where('start_date', '<=', $now)
            ->update(['status' => EventStatus::ONGOING->value]);

        $updatedCompleted = Event::where('status', '!=', EventStatus::COMPLETED->value)
            ->where('end_date', '<=', $now)
            ->update(['status' => EventStatus::COMPLETED->value]);

        $this->info("🔄 {$updatedOngoing} event(s) marked as ongoing.");
        $this->info("✅ {$updatedCompleted} event(s) marked as completed.");

        return Command::SUCCESS;
    }
}
