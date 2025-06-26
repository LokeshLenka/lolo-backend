<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\EventRegistration;
use Exception;
use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Carbon;

class EventRegistrationService
{
    public function generateQrCode(string $ticketCode): string
    {
        $qr = QrCode::format('png')->size(300)->generate($ticketCode);
        $qrBase64 = base64_encode($qr);

        return $qrBase64;
    }

    public function validateRegistration(User $user, Event $event): void
    {
        // Check if user is already registered for this event
        if (EventRegistration::where('user_id', $user->id)->where('event_id', $event->id)->exists()) {
            throw new Exception('You already registered for this event.');
        }

        // Check if registration deadline has passed
        if (Carbon::now()->greaterThan($event->registration_deadline)) {
            throw new Exception('Deadline reached.');
        }

        // Check if event has reached maximum capacity
        if (EventRegistration::where('event_id', $event->id)->count() >= $event->max_participants) {
            throw new Exception('Max registration limit reached.');
        }
    }

    public function isEligible(User $user, Event $event, string $eventType): bool
    {
        return match ($eventType) {

            // Club events require user to be approved and a club member
            'club'    => $event->type === 'club' && $user->isClubMember(),

            // Member events require user to be approved and a general member
            'music' => $event->type === 'music' && $user->isMusicMember(),

            default   => false,
        };
    }
}
