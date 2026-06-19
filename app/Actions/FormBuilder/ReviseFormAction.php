<?php

namespace App\Actions\FormBuilder;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Services\FormCodeService;
use App\Modules\FormBuilder\Services\FormVersioningService;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Services\WorkflowDuplicateService;
use Illuminate\Support\Facades\DB;

class ReviseFormAction
{
    public function __construct(
        private FormCodeService $codes,
        private FormVersioningService $versioner,
        private WorkflowDuplicateService $workflowDuplicator,
    ) {}

    public function execute(Form $original): Form
    {
        $original->loadMissing(['fields', 'permissions']);

        $familyCode = $original->form_family_code ?: $original->form_code;
        $nextVersion = $this->versioner->nextVersionForForm($original);
        $newFormCode = $this->codes->buildRevisionCode($familyCode, $nextVersion);

        $latestRevision = Form::withTrashed()
            ->where('form_family_code', $familyCode)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();

        $newForm = null;

        DB::transaction(function () use ($original, $familyCode, $nextVersion, $newFormCode, $latestRevision, &$newForm) {
            // Archive previous revisions in this family
            $siblingForms = Form::where('form_family_code', $familyCode)
                ->whereIn('status', ['Active', 'Inactive'])
                ->get();

            foreach ($siblingForms as $sibling) {
                Workflow::where('form_id', $sibling->id)
                    ->whereIn('status', ['Active', 'Draft'])
                    ->update(['status' => 'Archived']);

                $sibling->forceFill(['status' => 'Inactive', 'is_locked' => true])->save();
                $sibling->delete(); // soft-delete = Archived
            }

            /** @var Form $newForm */
            $newForm = Form::create([
                'form_name' => $original->form_name,
                'form_code' => $newFormCode,
                'form_family_code' => $familyCode,
                'parent_form_id' => $latestRevision?->id,
                'description' => $original->description,
                'form_category_id' => $original->form_category_id,
                'version' => $nextVersion,
                'revision_effective_at' => null,
                'status' => 'Inactive',
                'email_notifications' => (bool) $original->email_notifications,
                'submission_limit' => $original->submission_limit,
                'is_locked' => false,
                'created_by' => auth()->id(),
            ]);

            $this->replicateFields($original, $newForm);
            $newForm->permissions()->sync($original->permissions->pluck('id')->all());

            // Clone the workflow from the source form (if any)
            $sourceWorkflow = Workflow::where('form_id', $original->id)
                ->orderByRaw("CASE status WHEN 'Active' THEN 1 WHEN 'Archived' THEN 2 ELSE 3 END")
                ->first();

            if ($sourceWorkflow) {
                $sourceWorkflow->loadMissing('steps.approvers');
                $copy = $this->workflowDuplicator->duplicate($sourceWorkflow);
                $copy->update([
                    'form_id' => $newForm->id,
                    'status' => 'Draft',
                    'workflow_name' => $sourceWorkflow->workflow_name,
                ]);
            }
        });

        return $newForm;
    }

    private function replicateFields(Form $source, Form $target): void
    {
        foreach ($source->fields as $field) {
            $payload = $field->toArray();
            unset($payload['id'], $payload['date_created']);
            $payload['form_id'] = $target->id;
            $target->fields()->create($payload);
        }
    }
}
