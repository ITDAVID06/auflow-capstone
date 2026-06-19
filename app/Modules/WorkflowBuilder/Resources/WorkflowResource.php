<?php

namespace App\Modules\WorkflowBuilder\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'workflow_name' => $this->workflow_name,
            'workflow_type' => $this->workflow_type,
            'status' => $this->status,
            'description' => $this->description,
            'form' => $this->form ? [
                'id' => $this->form->id,
                'name' => $this->form->form_name,
            ] : null,
            'steps' => WorkflowStepResource::collection($this->whenLoaded('steps')),
            'workflow_settings' => $this->workflow_settings,
        ];
    }
}
