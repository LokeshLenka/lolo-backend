<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamProfile extends Model
{
    protected $fillable = [
        'job_title',
        'job_description'
    ];

    protected $hidden = [
        'id',
        'user_id'
    ];

    /**
     * Relations
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasOne
    {
        return $this->hasOne(Image::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
