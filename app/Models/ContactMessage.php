<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $table = 'contact_messages';

    protected $fillable = [
        'name',
        'email',
        'role',
        // 'custom_role',
        'message',
    ];

    // ✅ Cast custom_role to nullable string
    protected $casts = [
        'custom_role' => 'string|null',
    ];

    // ✅ Auto-trim strings on save
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($message) {
            $message->name = trim($message->name);
            $message->email = strtolower(trim($message->email));
            $message->message = trim($message->message);
        });
    }
}
