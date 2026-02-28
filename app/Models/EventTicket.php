<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'public_user_id',
        'event_id',
        'reg_num',
        'ticket_code',
        'is_verified',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function publicUser()
    {
        return $this->belongsTo(PublicUser::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isVerified(): bool
    {
        return $this->verified_at !== null && $this->is_verified;
    }
}
