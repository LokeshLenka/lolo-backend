<?php

namespace App\Models;

use Brick\Math\BigInteger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserApproval extends Model
{
    protected $fillable = [
        'status',
        'approved_by'
    ];

    public function getApprovalStatus(): string
    {
        return $this->status;
    }

    public function getApprovedAt(): string
    {
        return !empty((string)($this->approved_at)) ? $this->approved_at : "Approved at not found!";
    }

    public function getUserId(): BigInteger
    {
        return !empty($this->user_id) ? $this->user_id : -1;
    }

    public function getApprovedUserId(): BigInteger
    {
        return !empty($this->approved_by) ? $this->approved_by : -1;
    }

    public function getCreatedAt(): string
    {
        return !empty($this->created_at)
            ? $this->created_at->format('d M Y, h:i A')
            : 'Not Available';
    }

    public function getUpdatedAt(): string
    {
        return !empty($this->updated_at)
            ? $this->updated_at->format('d M Y, h:i A')
            : 'Not Available';
    }

    public function getDeletedAt(): string
    {
        return !empty($this->deleted_at)
            ? $this->deleted_at->format('d M Y, h:i A')
            : 'Not Available';
    }


    //relations
    public function user(): BelongsTo
    {
        return $this->BelongsTo(User::class);
    }
}
