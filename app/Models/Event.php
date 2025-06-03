<?php

namespace App\Models;

use Faker\Core\Coordinates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\EventStatus;
use App\Enums\RegistrationMode;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'coordinator1',
        'coordinator2',
        'coordinator3',
        'name',
        'description',
        'type',
        'timings',
        'venue',
        'status',
        'credits_awarded',
        'registration_deadline',
        'max_participants',
        'registration_mode',
        'registration_place',
    ];


    protected $casts = [
        'name' => 'string',
        'description' => 'string',
        'timings' => 'datetime',
        'venue' => 'string',
        'status' => EventStatus::class,
        'registration_deadline' => 'datetime',
        'credits_awarded' => 'double',
        'max_participants' => 'int',
        'registration_mode' => RegistrationMode::class,
        'registration_place' => 'string'
    ];


    public function getCoordinators(): array
    {
        $coordinators = [
            $this->coordinator1 ?? null,
            $this->coordinator2 ?? null,
            $this->coordinator3 ?? null,
        ];

        // Optional: Remove null values
        return array_values(array_filter($coordinators, fn($c) => !is_null($c)));
    }

    public function getRegistrationMode(): string
    {
        return $this->registration_mode === 'online' ? 'online' : 'offline';
    }

    /** Relations */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function credit(): HasOne
    {
        return $this->hasOne(Credit::class);
    }
}
