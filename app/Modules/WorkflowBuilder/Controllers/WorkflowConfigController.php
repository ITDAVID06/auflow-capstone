<?php

namespace App\Modules\WorkflowBuilder\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\WorkflowBuilder\Services\WorkflowService;

class WorkflowConfigController extends Controller
{
    public function forms(WorkflowService $svc)
    {
        return response()->json($svc->getAvailableForms());
    }

    public function users(WorkflowService $svc)
    {
        return response()->json($svc->getAssignableUsers());
    }

    public function fields(int $id)
    {
        $form = Form::with('fields')->findOrFail($id);

        return response()->json([
            'fields' => $form->fields->map(fn ($f) => [
                'id' => $f->id,
                'field_name' => $f->field_name,
                'label' => $f->label,
                'data_type' => $f->data_type,
            ])->values(),
        ]);
    }
}
