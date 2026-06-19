<?php

namespace App\Services\DemoSeeding;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormCategory;
use App\Modules\FormBuilder\Models\FormField;
use Illuminate\Support\Facades\DB;

class DemoFormSeeder
{
    /**
     * @return array<int, Form>
     */
    public function seed(int $adminAccountId, DemoSeedProfile $profile): array
    {
        $categories = $this->seedCategories();

        $forms = [];
        for ($i = 1; $i <= $profile->formCount; $i++) {
            $category = $categories[($i - 1) % count($categories)];
            $formCode = sprintf('DEMO-FORM-%02d', $i);

            $form = Form::query()->updateOrCreate(
                ['form_code' => $formCode],
                [
                    'form_name' => sprintf('Demo Request Form %02d', $i),
                    'form_family_code' => $formCode,
                    'parent_form_id' => null,
                    'description' => sprintf('Synthetic demo form %02d for AUFlow walkthroughs.', $i),
                    'form_category_id' => $category->id,
                    'form_type' => 'demo',
                    'version' => 1,
                    'revision_effective_at' => now()->toDateString(),
                    'status' => 'Active',
                    'email_notifications' => true,
                    'submission_limit' => null,
                    'is_locked' => true,
                    'draft_data' => ['source' => 'seed:demo'],
                    'created_by' => $adminAccountId,
                ]
            );

            $this->seedFieldsForForm($form, $i);
            $this->seedPermissionsForForm($form->id);

            $forms[] = $form;
        }

        return $forms;
    }

    /**
     * @return array<int, FormCategory>
     */
    private function seedCategories(): array
    {
        $categoryRows = [
            ['name' => 'Academic', 'slug' => 'academic'],
            ['name' => 'Administrative', 'slug' => 'administrative'],
            ['name' => 'Facilities', 'slug' => 'facilities'],
        ];

        $categories = [];
        foreach ($categoryRows as $row) {
            $categories[] = FormCategory::query()->updateOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name']]
            );
        }

        return $categories;
    }

    private function seedFieldsForForm(Form $form, int $formIndex): void
    {
        $fieldDefinitions = $this->resolveFieldTemplate($formIndex);

        foreach ($fieldDefinitions as $field) {
            FormField::query()->updateOrCreate(
                [
                    'form_id' => $form->id,
                    'field_name' => $field['field_name'],
                ],
                [
                    'label' => $field['label'],
                    'data_type' => $field['data_type'],
                    'is_required' => $field['is_required'],
                    'field_order' => $field['field_order'],
                    'placeholder' => $field['placeholder'],
                    'help_text' => $field['help_text'],
                    'options' => $field['options'],
                    'options_meta' => $field['options_meta'],
                    'field_options' => $field['field_options'],
                    'conditions' => $field['conditions'],
                    'use_slots' => $field['use_slots'],
                    'require_facility' => $field['require_facility'],
                    'date_mode' => $field['date_mode'],
                    'is_publicly_verifiable' => $field['is_publicly_verifiable'] ?? true,
                    'is_sensitive' => $field['is_sensitive'] ?? false,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveFieldTemplate(int $formIndex): array
    {
        $templates = [
            [
                [
                    'field_name' => 'request_reason',
                    'label' => 'Reason for Request',
                    'data_type' => 'textarea',
                    'is_required' => true,
                    'field_order' => 1,
                    'placeholder' => 'State the purpose of this request',
                    'help_text' => 'This is used in workflow review.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'requested_date',
                    'label' => 'Requested Date',
                    'data_type' => 'date',
                    'is_required' => true,
                    'field_order' => 2,
                    'placeholder' => null,
                    'help_text' => 'Select your preferred processing date.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => 'single',
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'participants_count',
                    'label' => 'Number of Participants',
                    'data_type' => 'number',
                    'is_required' => false,
                    'field_order' => 3,
                    'placeholder' => '0',
                    'help_text' => null,
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['min' => 1, 'max' => 500],
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'preferred_facility',
                    'label' => 'Preferred Facility',
                    'data_type' => 'select',
                    'is_required' => false,
                    'field_order' => 4,
                    'placeholder' => null,
                    'help_text' => 'Optional facility preference for this request.',
                    'options' => ['Auditorium', 'Gymnasium', 'Library Hall'],
                    'options_meta' => [
                        ['value' => 'Auditorium', 'campus' => 'Main', 'capacity' => 420],
                        ['value' => 'Gymnasium', 'campus' => 'Main', 'capacity' => 350],
                        ['value' => 'Library Hall', 'campus' => 'North', 'capacity' => 180],
                    ],
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => true,
                    'require_facility' => true,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'supporting_notes',
                    'label' => 'Supporting Notes',
                    'data_type' => 'text',
                    'is_required' => false,
                    'field_order' => 5,
                    'placeholder' => 'Additional details',
                    'help_text' => null,
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => [
                        'all' => [
                            ['field' => 'participants_count', 'operator' => '>=', 'value' => 100],
                        ],
                    ],
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
            ],
            [
                [
                    'field_name' => 'request_reason',
                    'label' => 'Reason for Request',
                    'data_type' => 'textarea',
                    'is_required' => true,
                    'field_order' => 1,
                    'placeholder' => 'Summarize the request context',
                    'help_text' => 'Reviewers use this summary to triage requests.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['min_rows' => 3, 'max_rows' => 8],
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'event_window',
                    'label' => 'Requested Event Window',
                    'data_type' => 'date',
                    'is_required' => true,
                    'field_order' => 2,
                    'placeholder' => null,
                    'help_text' => 'Choose a date range for the request.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => 'range',
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'contact_email',
                    'label' => 'Contact Email',
                    'data_type' => 'email',
                    'is_required' => true,
                    'field_order' => 3,
                    'placeholder' => 'name@auf.edu.ph',
                    'help_text' => 'Approvers will send updates to this address.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'request_channel',
                    'label' => 'Request Channel',
                    'data_type' => 'radio',
                    'is_required' => true,
                    'field_order' => 4,
                    'placeholder' => null,
                    'help_text' => null,
                    'options' => ['Student Council', 'Department', 'Organization'],
                    'options_meta' => [
                        ['value' => 'Student Council', 'route_to' => 'Dean Office'],
                        ['value' => 'Department', 'route_to' => 'Department Chair'],
                        ['value' => 'Organization', 'route_to' => 'Student Affairs'],
                    ],
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'required_resources',
                    'label' => 'Required Resources',
                    'data_type' => 'checkbox',
                    'is_required' => false,
                    'field_order' => 5,
                    'placeholder' => null,
                    'help_text' => 'Select all resources needed for the request.',
                    'options' => ['Projector', 'Sound System', 'Tables'],
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'attachment_reference',
                    'label' => 'Attachment Reference',
                    'data_type' => 'file',
                    'is_required' => false,
                    'field_order' => 6,
                    'placeholder' => null,
                    'help_text' => 'Optional supporting file.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 10],
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'resource_notes',
                    'label' => 'Resource Notes',
                    'data_type' => 'text',
                    'is_required' => false,
                    'field_order' => 7,
                    'placeholder' => 'Add setup notes if specific resources are needed',
                    'help_text' => null,
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => [
                        'any' => [
                            ['field' => 'required_resources', 'operator' => 'contains', 'value' => 'Projector'],
                            ['field' => 'required_resources', 'operator' => 'contains', 'value' => 'Sound System'],
                        ],
                    ],
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
            ],
            [
                [
                    'field_name' => 'request_reason',
                    'label' => 'Reason for Request',
                    'data_type' => 'textarea',
                    'is_required' => true,
                    'field_order' => 1,
                    'placeholder' => 'Describe what this event or activity is for',
                    'help_text' => 'Provide enough context for both approving offices.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['min_rows' => 4, 'max_rows' => 10],
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'requested_schedule',
                    'label' => 'Requested Schedule',
                    'data_type' => 'date',
                    'is_required' => true,
                    'field_order' => 2,
                    'placeholder' => null,
                    'help_text' => 'Pick a start and end date for schedule planning.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['allow_weekend' => false],
                    'conditions' => null,
                    'use_slots' => true,
                    'require_facility' => true,
                    'date_mode' => 'range',
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'coordinator_phone',
                    'label' => 'Coordinator Phone Number',
                    'data_type' => 'phone',
                    'is_required' => true,
                    'field_order' => 3,
                    'placeholder' => '+639171234567',
                    'help_text' => 'For same-day clarifications from approvers.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['format' => 'ph-mobile'],
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    // PII: visible on the public verification page, but partially masked.
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => true,
                ],
                [
                    'field_name' => 'urgency_level',
                    'label' => 'Urgency Level',
                    'data_type' => 'select',
                    'is_required' => true,
                    'field_order' => 4,
                    'placeholder' => null,
                    'help_text' => 'Impacts SLA targets and reminder cadence.',
                    'options' => ['Low', 'Standard', 'Urgent'],
                    'options_meta' => [
                        ['value' => 'Low', 'sla_hours' => 72],
                        ['value' => 'Standard', 'sla_hours' => 48],
                        ['value' => 'Urgent', 'sla_hours' => 24],
                    ],
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'budget_estimate',
                    'label' => 'Budget Estimate',
                    'data_type' => 'number',
                    'is_required' => false,
                    'field_order' => 5,
                    'placeholder' => '0',
                    'help_text' => 'Estimated request budget in PHP.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['min' => 0, 'max' => 200000, 'step' => 500],
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'transport_required',
                    'label' => 'Transport Required',
                    'data_type' => 'radio',
                    'is_required' => true,
                    'field_order' => 6,
                    'placeholder' => null,
                    'help_text' => null,
                    'options' => ['No', 'Yes'],
                    'options_meta' => [
                        ['value' => 'No', 'requires_additional_note' => false],
                        ['value' => 'Yes', 'requires_additional_note' => true],
                    ],
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'transport_notes',
                    'label' => 'Transport Notes',
                    'data_type' => 'text',
                    'is_required' => false,
                    'field_order' => 7,
                    'placeholder' => 'Pickup location and departure time',
                    'help_text' => null,
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => [
                        'all' => [
                            ['field' => 'transport_required', 'operator' => 'equals', 'value' => 'Yes'],
                        ],
                    ],
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'home_address',
                    'label' => 'Home Address',
                    'data_type' => 'text',
                    'is_required' => false,
                    'field_order' => 8,
                    'placeholder' => 'Street, City, Province',
                    'help_text' => 'For official correspondence only — not displayed on the public verification page.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    // Fully redacted on the public verification page.
                    'is_publicly_verifiable' => false,
                    'is_sensitive' => false,
                ],
            ],
            [
                [
                    'field_name' => 'request_reason',
                    'label' => 'Reason for Request',
                    'data_type' => 'textarea',
                    'is_required' => true,
                    'field_order' => 1,
                    'placeholder' => 'Summarize this request clearly',
                    'help_text' => null,
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['min_rows' => 3, 'max_rows' => 6],
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'proposed_start_date',
                    'label' => 'Proposed Start Date',
                    'data_type' => 'date',
                    'is_required' => true,
                    'field_order' => 2,
                    'placeholder' => null,
                    'help_text' => null,
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['allow_weekend' => true],
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => 'single',
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'proposed_end_date',
                    'label' => 'Proposed End Date',
                    'data_type' => 'date',
                    'is_required' => true,
                    'field_order' => 3,
                    'placeholder' => null,
                    'help_text' => null,
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['allow_weekend' => true],
                    'conditions' => [
                        'all' => [
                            ['field' => 'proposed_start_date', 'operator' => 'is_not_empty', 'value' => true],
                        ],
                    ],
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => 'single',
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'requester_email',
                    'label' => 'Requester Email',
                    'data_type' => 'email',
                    'is_required' => true,
                    'field_order' => 4,
                    'placeholder' => 'name@auf.edu.ph',
                    'help_text' => null,
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'preferred_contact_method',
                    'label' => 'Preferred Contact Method',
                    'data_type' => 'radio',
                    'is_required' => true,
                    'field_order' => 5,
                    'placeholder' => null,
                    'help_text' => null,
                    'options' => ['Email', 'Phone', 'Teams'],
                    'options_meta' => [
                        ['value' => 'Email', 'requires' => ['requester_email']],
                        ['value' => 'Phone', 'requires' => ['coordinator_phone']],
                        ['value' => 'Teams', 'requires' => ['requester_email']],
                    ],
                    'field_options' => null,
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'compliance_acknowledgement',
                    'label' => 'Compliance Acknowledgement',
                    'data_type' => 'checkbox',
                    'is_required' => true,
                    'field_order' => 6,
                    'placeholder' => null,
                    'help_text' => 'Confirm policy awareness before submission.',
                    'options' => ['I confirm policy compliance'],
                    'options_meta' => [
                        ['value' => 'I confirm policy compliance', 'policy_version' => '2026.1'],
                    ],
                    'field_options' => ['min_selected' => 1],
                    'conditions' => null,
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
                [
                    'field_name' => 'attachment_reference',
                    'label' => 'Attachment Reference',
                    'data_type' => 'file',
                    'is_required' => false,
                    'field_order' => 7,
                    'placeholder' => null,
                    'help_text' => 'Attach additional compliance document if available.',
                    'options' => null,
                    'options_meta' => null,
                    'field_options' => ['accept' => ['pdf', 'docx'], 'max_size_mb' => 5],
                    'conditions' => [
                        'all' => [
                            ['field' => 'compliance_acknowledgement', 'operator' => 'contains', 'value' => 'I confirm policy compliance'],
                        ],
                    ],
                    'use_slots' => false,
                    'require_facility' => false,
                    'date_mode' => null,
                    'is_publicly_verifiable' => true,
                    'is_sensitive' => false,
                ],
            ],
        ];

        return $templates[($formIndex - 1) % count($templates)];
    }

    private function seedPermissionsForForm(int $formId): void
    {
        $permissionIds = DB::table('tbl_permission')
            ->whereIn('slug', ['forms.student-access', 'forms.staff-access', 'forms.public-access'])
            ->pluck('id')
            ->all();

        $rows = collect($permissionIds)
            ->map(fn (int $permissionId): array => [
                'form_id' => $formId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows !== []) {
            DB::table('tbl_form_permission')->upsert(
                $rows,
                ['form_id', 'permission_id'],
                ['updated_at']
            );
        }
    }
}
