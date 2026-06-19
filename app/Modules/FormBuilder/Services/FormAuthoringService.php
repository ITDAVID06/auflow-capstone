<?php

namespace App\Modules\FormBuilder\Services;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\WorkflowBuilder\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FormAuthoringService
{
    public function create(array $data, int $actorId): Form
    {
        return DB::transaction(function () use ($data, $actorId) {
            $isActive = ($data['status'] ?? 'Inactive') === 'Active';
            $version = (int) ($data['version'] ?? 1);
            $revisionEffectiveAt = $data['revision_effective_at'] ?? null;

            if ($isActive && $revisionEffectiveAt === null) {
                $revisionEffectiveAt = now()->toDateString();
            }

            $form = Form::create([
                'form_name' => $data['form_name'],
                'form_code' => $data['form_code'],
                'form_family_code' => $data['form_family_code'] ?? $data['form_code'],
                'description' => $data['description'] ?? null,
                'form_category_id' => $data['form_category_id'] ?? null,
                'version' => $version,
                'revision_effective_at' => $revisionEffectiveAt,
                'status' => $data['status'],
                'email_notifications' => (bool) ($data['email_notifications'] ?? false),
                'submission_limit' => $data['submission_limit'] ?? null,
                'sensitive_fields' => $data['sensitive_fields'] ?? null,
                'created_by' => $actorId,
                'is_locked' => $isActive,
            ]);

            foreach ($data['fields'] as $index => $field) {
                $form->fields()->create($this->fieldPayload($form->id, $field, $index));
            }

            $form->permissions()->sync($data['permissions'] ?? []);

            return $form;
        });
    }

    public function update(Form $form, array $data): Form
    {
        return DB::transaction(function () use ($form, $data) {
            if ($form->status === 'Active') {
                throw ValidationException::withMessages([
                    'form' => 'Active forms are read-only. Create a revision to continue editing this form.',
                ]);
            }

            $incomingFields = $data['fields'] ?? [];

            $form->update([
                'form_name' => $data['form_name'],
                'description' => $data['description'] ?? null,
                'form_category_id' => $data['form_category_id'] ?? null,
                'version' => $form->version,
                'status' => $data['status'],
                'email_notifications' => (bool) ($data['email_notifications'] ?? false),
                'submission_limit' => $data['submission_limit'] ?? null,
                'sensitive_fields' => $data['sensitive_fields'] ?? null,
            ]);

            $incomingIds = collect($incomingFields)
                ->pluck('id')
                ->filter()
                ->toArray();

            $form->fields()->whereNotIn('id', $incomingIds)->delete();

            foreach ($incomingFields as $index => $field) {
                $payload = $this->fieldPayload($form->id, $field, $index);

                if (! empty($field['id'])) {
                    $form->fields()->where('id', $field['id'])->update($payload);
                } else {
                    $form->fields()->create($payload);
                }
            }

            $form->permissions()->sync($data['permissions'] ?? []);

            if ($form->status === 'Active') {
                if (! $form->is_locked) {
                    $form->update(['is_locked' => true]);
                }
            }

            return $form->fresh(['fields', 'permissions']);
        });
    }

    /**
     * @return array{wasActivated: bool, wasDeactivated: bool}
     */
    public function updateStatus(Form $form, string $nextStatus): array
    {
        $wasInactive = $form->status !== 'Active' && $nextStatus === 'Active';
        $wasActive = $form->status === 'Active' && $nextStatus === 'Inactive';

        if ($wasInactive) {
            $hasActiveWorkflow = Workflow::query()
                ->where('form_id', $form->id)
                ->where('status', 'Active')
                ->exists();

            if (! $hasActiveWorkflow) {
                throw ValidationException::withMessages([
                    'status' => 'Forms can only be activated by publishing or enabling an associated workflow.',
                ]);
            }

            DB::transaction(function () use ($form, $nextStatus) {
                if ($nextStatus === 'Active' && ! $form->is_locked) {
                    $form->is_locked = true;
                }

                if ($nextStatus === 'Active' && $form->revision_effective_at === null) {
                    $form->revision_effective_at = now()->toDateString();
                }

                $form->status = $nextStatus;
                $form->save();
            });

            return ['wasActivated' => true, 'wasDeactivated' => false];
        }

        if ((in_array($nextStatus, ['Active', 'Inactive'], true)) && ! $form->is_locked) {
            $form->is_locked = true;
        }

        $form->status = $nextStatus;
        $form->save();

        if ($nextStatus === 'Inactive') {
            Workflow::where('form_id', $form->id)
                ->whereIn('status', ['Active'])
                ->update(['status' => 'Draft']);
        }

        return ['wasActivated' => false, 'wasDeactivated' => $wasActive];
    }

    public function archive(Form $form): void
    {
        DB::transaction(function () use ($form): void {
            $form->forceFill(['status' => 'Inactive'])->save();

            Workflow::where('form_id', $form->id)
                ->whereIn('status', ['Active'])
                ->update(['status' => 'Draft']);

            $form->delete();
        });
    }

    public function restore(Form $form): void
    {
        DB::transaction(function () use ($form): void {
            $form->restore();
            $form->forceFill(['status' => 'Inactive'])->save();
        });
    }

    private function fieldPayload(int $formId, array $field, int $index): array
    {
        return [
            'form_id' => $formId,
            'field_name' => $field['field_name'],
            'label' => $field['label'],
            'data_type' => $field['data_type'],
            'is_required' => (bool) ($field['is_required'] ?? false),
            'options' => $field['options'] ?? [],
            'options_meta' => $field['options_meta'] ?? null,
            'placeholder' => $field['placeholder'] ?? '',
            'help_text' => $field['help_text'] ?? null,
            'field_order' => $index,
            'use_slots' => (bool) ($field['use_slots'] ?? false),
            'require_facility' => (bool) ($field['require_facility'] ?? false),
            'date_mode' => $field['date_mode'] ?? 'single',
            'field_options' => $field['field_options'] ?? null,
            'conditions' => $field['conditions'] ?? null,
            'is_sensitive' => (bool) ($field['is_sensitive'] ?? false),
            'is_publicly_verifiable' => (bool) ($field['is_publicly_verifiable'] ?? true),
        ];
    }
}
