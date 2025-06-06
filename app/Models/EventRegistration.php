<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventRegistration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'event_id',
        'ticket_code',
        'registered_at',
        'registration_status',
        'is_paid',
        'payment_status',
        'payment_reference',
    ];

    protected $casts = [
        'ticket_code' => 'string',
        'registered_at' => 'datetime',
        'registered_status' => RegistrationStatus::class,
        'is_paid' => 'boolean',
        'payment_status' => PaymentStatus::class,
        'payment_reference' => 'string'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
