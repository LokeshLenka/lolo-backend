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
        'uuid',
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

    protected $hidden = [
        'id',
        'deleted_at',
        'user_id',
        'event_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCreatedBy($query, $createdBy)
    {
        return $query->where('user_id', $createdBy);
    }

    public function scopeEventId($query, $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeRegistrationStatus($query, $registrationStatus)
    {
        return $query->where('registration_status', $registrationStatus);
    }

    public function scopePaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopeIsPaid($query, bool $isPaid)
    {
        return $query->where('is_paid', $isPaid);
    }

    public function scopeTicketCode($query, $ticketCode)
    {
        return $query->where('ticket_code', $ticketCode);
    }
    public function scopeUuid($query, $uuid)
    {
        return $query->where('uuid', $uuid);
    }
    public function scopeRegisteredAt($query, $registeredAt)
    {
        return $query->where('registered_at', $registeredAt);
    }
    public function scopeCreatedAt($query, $createdAt)
    {
        return $query->where('created_at', $createdAt);
    }
    public function scopeUpdatedAt($query, $updatedAt)
    {
        return $query->where('updated_at', $updatedAt);
    }
    public function scopeDeletedAt($query, $deletedAt)
    {
        return $query->where('deleted_at', $deletedAt);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
