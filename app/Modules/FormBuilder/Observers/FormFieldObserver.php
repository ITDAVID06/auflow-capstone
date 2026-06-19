<?php

namespace App\Modules\FormBuilder\Observers;

use App\Modules\FormBuilder\Models\FormField;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;

class FormFieldObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(FormField $m): void
    {
        $this->forgetFormDefinitionCache($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('form_field_created', $m, 'Success', "Created Form Field {$name}", [
            'form_field_id' => $m->getKey(),
            'form_field_name' => $name,
            'form_id' => $m->form_id,
        ]);
    }

    public function updated(FormField $m): void
    {
        $this->forgetFormDefinitionCache($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('form_field_updated', $m, 'Success', "Updated Form Field {$name}", [
            'form_field_id' => $m->getKey(),
            'form_field_name' => $name,
            'form_id' => $m->form_id,
        ]);
    }

    public function deleted(FormField $m): void
    {
        $this->forgetFormDefinitionCache($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('form_field_deleted', $m, 'Warning', "Deleted Form Field {$name}", [
            'form_field_id' => $m->getKey(),
            'form_field_name' => $name,
            'form_id' => $m->form_id,
        ]);
    }

    private function nameOf(FormField $m): string
    {
        return $m->label ?? $m->name ?? (string) $m->getKey();
    }

    private function forgetFormDefinitionCache(FormField $field): void
    {
        if ($field->form_id) {
            Cache::forget('auflow:form:def:'.$field->form_id);
        }
    }
}
