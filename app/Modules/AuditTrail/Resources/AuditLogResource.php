<?php

namespace App\Modules\AuditTrail\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    // app/Modules/AuditTrail/Resources/AuditLogResource.php
    public function toArray($request)
    {
        $ref = $this->snapshot_public_id
            ?: ($this->auditable_type ? class_basename($this->auditable_type).' #'.$this->auditable_id : null);

        return [
            'id' => $this->id,
            'category' => $this->category,
            'action' => $this->action,
            'status' => $this->status ?? 'Info',
            'description' => $this->description ?? ucfirst(str_replace('_', ' ', $this->action)),
            'actor' => [
                'id' => $this->actor_id,
                'name' => $this->actor_name ?: ($this->actor_email ?: 'System'),
                'email' => $this->actor_email,
                'role' => $this->actor_role,
            ],
            'ref' => $ref,                           // <= never blank if we can infer
            'ip' => $this->ip_address ?? '—',
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'qr' => ['result' => $this->verification_result, 'payload' => $this->qr_payload],
            'metadata' => $this->metadata,
        ];
    }
}
