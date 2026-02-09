<?php

namespace App\Models;

use App\Enums\IsPaid;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicRegistration extends Model
{
    protected $fillable = [
        'reg_num',
        'event_id',
        'ticket_code',
        'is_paid',
        'payment_status',
        'registration_status'
    ];

    protected $casts = [
        'ticket_code' => 'string',
        'is_paid' => IsPaid::class,
        'payment_status' => PaymentStatus::class,
        'registration_status' => RegistrationStatus::class,
    ];

    public function publicUser(): BelongsTo
    {
        return $this->belongsTo(PublicUser::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
