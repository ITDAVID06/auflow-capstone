<?php

namespace App\Modules\UserManagement\Resources;

use App\Services\ProfilePictureUrlResolver;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        $profilePictureUrl = app(ProfilePictureUrlResolver::class)
            ->resolve(optional($this->profile)->profile_picture);

        return [
            'account_id' => $this->account_id,
            'name' => $this->name ?? $this->username,
            'full_name' => $this->full_name ?? trim(($this->profile->first_name ?? '').' '.($this->profile->last_name ?? '')),
            'email' => $this->email,
            'created_at' => $this->created_at?->format('Y-m-d'),

            'status' => [
                'id' => optional($this->status)->id,
                'status_name' => optional($this->status)->status_name,
            ],

            'profile' => [
                'first_name' => optional($this->profile)->first_name,
                'last_name' => optional($this->profile)->last_name,
                'middle_name' => optional($this->profile)->middle_name,
                'student_id' => optional($this->profile)->student_id,
                'employee_id' => optional($this->profile)->employee_id,
                'phone' => optional($this->profile)->phone,
                'address' => optional($this->profile)->address,
                'date_of_birth' => optional($this->profile)->date_of_birth,
                'gender' => optional($this->profile)->gender,
                'profile_picture' => optional($this->profile)->profile_picture,
                'profile_picture_url' => $profilePictureUrl,
            ],

            'roles' => $this->roles
                ->map(fn ($r) => [
                    'role_id' => $r->role_id,
                    'role_name' => optional($r->role)->role_name,
                ])
                ->filter()
                ->values(),
        ];
    }
}
