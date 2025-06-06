<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EventRegistrationService
{
    public function generateQrCode(string $ticketCode): string
    {
        $qr = QrCode::format('png')->size(300)->generate($ticketCode);
        $qrBase64 = base64_encode($qr);

        return $qrBase64;
    }
}
