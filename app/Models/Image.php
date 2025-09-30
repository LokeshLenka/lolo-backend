<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'uploaded_by',
        'path',
        'img_type',
        'alt_txt'
    ];

    protected $hidden = [
        'id',
        'deleted_at'
    ];

    protected $appends = ['url'];

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_images')
            ->withTimestamps();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->path);
    }
}
