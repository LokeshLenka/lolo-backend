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
        // Check if the event relationship is loaded to avoid N+1 issues or null errors
        // (It should be loaded based on your Cache::remember query)
        $event = $this->whenLoaded('event');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,

            // Matches interface `user_id: number`
            'user_id' => $this->user_id,

            // Matches interface `event_id: number`
            'event_id' => $this->event_id,

            'amount' => $this->amount,
            'assigned_by' => $this->assigned_by,

            // Matches interface `created_at: string` and `updated_at: string`
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Matches interface `event: Event`
            'event' => $event ? [
                'id' => $event->id,
                'uuid' => $event->uuid,
                'name' => $event->name,
                'credits_awarded' => $event->credits_awarded,
                'end_date' => $event->end_date,
            ] : null,
        ];
    }
}
