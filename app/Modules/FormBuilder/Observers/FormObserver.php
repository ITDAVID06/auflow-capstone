<?php

namespace App\Modules\FormBuilder\Observers;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;

class FormObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(Form $m): void
    {
        $this->flushDefinitionCaches($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('form_created', $m, 'Success', "Created Form {$name}", [
            'form_id' => $m->getKey(),
            'form_name' => $name,
        ]);
    }

    public function updated(Form $m): void
    {
        $this->flushDefinitionCaches($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('form_updated', $m, 'Success', "Updated Form {$name}", [
            'form_id' => $m->getKey(),
            'form_name' => $name,
        ]);
    }

    public function deleted(Form $m): void
    {
        $this->flushDefinitionCaches($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('form_deleted', $m, 'Warning', "Deleted Form {$name}", [
            'form_id' => $m->getKey(),
            'form_name' => $name,
        ]);
    }

    private function nameOf(Form $m): string
    {
        return $m->form_name ?? (string) $m->getKey();
    }

    private function flushDefinitionCaches(Form $form): void
    {
        Cache::forget('auflow:form:def:'.$form->getKey());
        Cache::forget(WorkflowService::availableFormsCacheKey());
    }
}
