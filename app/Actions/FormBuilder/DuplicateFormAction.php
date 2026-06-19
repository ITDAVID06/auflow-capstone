<?php

namespace App\Actions\FormBuilder;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Services\FormCodeService;
use Illuminate\Support\Facades\DB;

class DuplicateFormAction
{
    public function __construct(private FormCodeService $codes) {}

    public function execute(Form $original): Form
    {
        $familyCode = $this->codes->nextFamilyCode();
        $newFormCode = $this->codes->buildRevisionCode($familyCode, 1);
        $newName = $this->nextCopyName($original->form_name);

        $newForm = null;

        DB::transaction(function () use ($original, $newName, $newFormCode, $familyCode, &$newForm) {
            /** @var Form $newForm */
            $newForm = Form::create([
                'form_name' => $newName,
                'form_code' => $newFormCode,
                'form_family_code' => $familyCode,
                'parent_form_id' => null,
                'description' => null,
                'form_category_id' => null,
                'version' => 1,
                'revision_effective_at' => null,
                'status' => 'Inactive',
                'email_notifications' => false,
                'submission_limit' => null,
                'is_locked' => false,
                'created_by' => auth()->id(),
            ]);

            $this->replicateFields($original, $newForm);
        });

        return $newForm;
    }

    private function nextCopyName(string $originalName): string
    {
        $baseName = preg_replace('/ - Copy(?: \d+)?$/', '', $originalName) ?: $originalName;
        $candidate = $baseName.' - Copy';
        $suffix = 2;

        while (Form::withTrashed()->where('form_name', $candidate)->exists()) {
            $candidate = $baseName.' - Copy '.$suffix;
            $suffix++;
        }

        return $candidate;
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
