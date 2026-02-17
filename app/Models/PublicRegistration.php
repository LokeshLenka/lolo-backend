<?php

namespace App\Models;

use App\Enums\IsPaid;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PublicRegistration extends Model
{
    protected $fillable = [
        'uuid',
        'public_user_id',
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
    
    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
