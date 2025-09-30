<?php

namespace App\Http\Resources;

use Auth;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'management_level' => $this->management_level,
            'promoted_role' => $this->promoted_role,
            'registration_type' => $this->registration_type,
            'is_registered_by_me' => $this->created_by === Auth::id(),
            'profile' => $this->whenLoaded('musicProfile') ?: $this->whenLoaded('managementProfile'),
            'user_approval' => $this->whenLoaded('userApproval'),
            'created_by' => $this->whenLoaded('createdBy', fn() => [
                'username' => $this->createdBy?->username,
            ]),
        ];
    }
}
