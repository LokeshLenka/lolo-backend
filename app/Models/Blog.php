<?php

namespace App\Models;

use App\Enums\BlogStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blog extends Model
{
    /** @use HasFactory<\Database\Factories\BlogFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'status'
    ];

    protected $casts = [
        'title' => 'string',
        'content' => 'string',
        'status' => BlogStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username');
    }
}
