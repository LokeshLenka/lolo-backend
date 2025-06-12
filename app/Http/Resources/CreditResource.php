<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class CreditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = Auth::user() && Auth::user()->canManageCredits();

        return [
            'id' => $this->id,
            'user' => $this->user_id,
            'event' => $this->event_id,
            'amount' => $this->amount,
            'assigned_by' => $this->assigned_by,

            // Only show timestamps if admin
            $this->mergeWhen($isAdmin, [
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]),
        ];
    }
}
