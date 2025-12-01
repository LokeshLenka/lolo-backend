<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            ...$this->only([
                'uuid',
                'user_id',
                'coordinator1',
                'coordinator2',
                'coordinator3',
                'name',
                'description',
                'type',
                'start_date',
                'end_date',
                'venue',
                'status',
                'credits_awarded',
                'fee',
                'registration_deadline',
                'max_participants',
                'registration_mode',
                'registration_place',
            ]),

            'images' => $this->images->map(function ($img) {
                return [
                    'uuid'      => $img->uuid,
                    'url'       => $img->url,              // final full URL
                    'img_type'  => $img->img_type,
                    'alt_txt'   => $img->alt_txt,
                    'uploaded_by' => $img->uploaded_by,
                    'created_at' => $img->created_at,
                ];
            }),


            'coordinators' => [
                $this->extractCoordinator($this->coordinatorOneUser),
                $this->extractCoordinator($this->coordinatorTwoUser),
                $this->extractCoordinator($this->coordinatorThreeUser),
            ],
        ];
    }

    private function extractCoordinator($user)
    {
        if (!$user) {
            return null;
        }

        $profile = $user->managementProfile ?? $user->musicProfile ?? null;

        $fullName = $profile
            ? trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''))
            : null;

        return [
            'name' => $fullName ?: $user->username,
            'phone' => $profile->phone_no ?? null,
            'role' => $user->promoted_role ?? null, // Added promoted_role here
        ];
    }
}
