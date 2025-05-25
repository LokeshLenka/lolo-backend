<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Token extends Model
{
    protected $fillable = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
