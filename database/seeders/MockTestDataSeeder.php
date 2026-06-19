<?php

namespace Database\Seeders;

use App\Modules\ErrorReports\Models\ErrorReport;
use App\Modules\FormBuilder\Models\Facility;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormCategory;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\Slot;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserProfile;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\UserManagement\Models\UserStatus;
use App\Modules\VerificationSnapshot\Services\SnapshotService;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepApprover;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use App\Modules\WorkflowBuilder\Support\WorkflowConditionEvaluator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MockTestDataSeeder extends Seeder
{
    /**
     * Generate comprehensive mock data for end-to-end testing.
     *
     * Depends on: PermissionSeeder (roles, permissions, user statuses must exist).
     * Safe to re-run — uses updateOrCreate / firstOrCreate for idempotency.
     */
    public function run(): void
    {
        $this->command->info('Starting MockTestDataSeeder...');

        $staffUsers = $this->seedStaffUsers();
        $studentUsers = $this->seedStudentUsers();
        $categories = $this->seedFormCategories();
        $this->seedFacilities();
        $forms = $this->seedForms($categories, $staffUsers);
        [$workflows, $versions] = $this->seedWorkflows($forms, $staffUsers);
        $this->seedSubmissions($forms, $versions, $studentUsers);
        $this->seedSnapshots($forms);
        $this->seedSlots($forms);
        $this->seedErrorReports(array_merge($staffUsers, $studentUsers));

        $this->command->info('MockTestDataSeeder complete.');
    }

    // ─── Staff Users ─────────────────────────────────────────────────────────

    /** @return array<int, User> */
    private function seedStaffUsers(): array
    {
        $this->command->info('  → Staff users...');

        $activeStatusId = UserStatus::where('status_name', 'Active')->value('id');
        $staffRoleId = Role::where('role_name', 'Staff')->value('id');
        $password = Hash::make('password');
        $today = now()->toDateString();

        $definitions = [
            [
                'email' => 'auflow.auf@gmail.com',
                'username' => 'maria.santos',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'middle_name' => 'L.',
                'employee_id' => 'EMP1001',
                'department' => 'Registrar Office',
                'position' => 'University Registrar',
                'gender' => 'Female',
            ],
            [
                'email' => 'kevsmiranda07@gmail.com',
                'username' => 'kevin.miranda07',
                'first_name' => 'Kevin',
                'last_name' => 'Miranda',
                'middle_name' => 'A.',
                'employee_id' => 'EMP1002',
                'department' => 'Information Technology',
                'position' => 'IT Administrator',
                'gender' => 'Male',
            ],
            [
                'email' => 'kevsmiranda08@gmail.com',
                'username' => 'kevin.miranda08',
                'first_name' => 'Kevin',
                'last_name' => 'Miranda II',
                'middle_name' => 'B.',
                'employee_id' => 'EMP1003',
                'department' => 'Administration',
                'position' => 'Administrative Officer',
                'gender' => 'Male',
            ],
            [
                'email' => 'kevsmir02@gmail.com',
                'username' => 'kevin.santos02',
                'first_name' => 'Kevin',
                'last_name' => 'Santos',
                'middle_name' => 'C.',
                'employee_id' => 'EMP1004',
                'department' => 'Finance',
                'position' => 'Finance Officer',
                'gender' => 'Male',
            ],
            [
                'email' => 'miranda.kevin@student.auf.edu.ph',
                'username' => 'miranda.kevin.sa',
                'first_name' => 'Miranda',
                'last_name' => 'Kevin',
                'middle_name' => null,
                'employee_id' => 'EMP1005',
                'department' => 'Student Affairs',
                'position' => 'Student Affairs Coordinator',
                'gender' => 'Male',
            ],
        ];

        $users = [];
        foreach ($definitions as $def) {
            $user = User::updateOrCreate(
                ['email' => $def['email']],
                [
                    'username' => $def['username'],
                    'password' => $password,
                    'must_change_password' => false,
                    'user_status_id' => $activeStatusId,
                ]
            );

            UserProfile::updateOrCreate(
                ['account_id' => $user->account_id],
                [
                    'first_name' => $def['first_name'],
                    'last_name' => $def['last_name'],
                    'middle_name' => $def['middle_name'],
                    'employee_id' => $def['employee_id'],
                    'department' => $def['department'],
                    'position' => $def['position'],
                    'gender' => $def['gender'],
                    'phone' => '+639'.fake()->numerify('#########'),
                    'date_of_birth' => fake()->dateTimeBetween('-50 years', '-25 years')->format('Y-m-d'),
                    'address' => 'AUF Campus, Angeles City, Pampanga',
                ]
            );

            if ($staffRoleId) {
                UserRole::updateOrCreate(
                    ['account_id' => $user->account_id, 'role_id' => $staffRoleId],
                    ['assigned_date' => $today, 'is_active' => 1, 'assigned_by' => $user->account_id]
                );
            }

            $users[] = $user;
        }

        return $users;
    }

    /** @return array<int, User> */
    private function seedStudentUsers(): array
    {
        $this->command->info('  → Student users...');

        $activeStatusId = UserStatus::where('status_name', 'Active')->value('id');
        $studentRoleId = Role::where('role_name', 'Student')->value('id');
        $password = Hash::make('password');
        $today = now()->toDateString();

        $definitions = [
            ['email' => 'student.juan@auf.edu.ph', 'username' => 'juan.delacruz', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'student_id' => '2021-00001', 'gender' => 'Male'],
            ['email' => 'student.ana@auf.edu.ph', 'username' => 'ana.reyes', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'student_id' => '2021-00002', 'gender' => 'Female'],
            ['email' => 'student.pedro@auf.edu.ph', 'username' => 'pedro.garcia', 'first_name' => 'Pedro', 'last_name' => 'Garcia', 'student_id' => '2021-00003', 'gender' => 'Male'],
            ['email' => 'student.maria@auf.edu.ph', 'username' => 'maria.lopez', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'student_id' => '2022-00001', 'gender' => 'Female'],
            ['email' => 'student.carlo@auf.edu.ph', 'username' => 'carlo.mendoza', 'first_name' => 'Carlo', 'last_name' => 'Mendoza', 'student_id' => '2022-00002', 'gender' => 'Male'],
            ['email' => 'student.liza@auf.edu.ph', 'username' => 'liza.rivera', 'first_name' => 'Liza', 'last_name' => 'Rivera', 'student_id' => '2022-00003', 'gender' => 'Female'],
            ['email' => 'student.jose@auf.edu.ph', 'username' => 'jose.santos', 'first_name' => 'Jose', 'last_name' => 'Santos', 'student_id' => '2023-00001', 'gender' => 'Male'],
            ['email' => 'student.nina@auf.edu.ph', 'username' => 'nina.flores', 'first_name' => 'Nina', 'last_name' => 'Flores', 'student_id' => '2023-00002', 'gender' => 'Female'],
        ];

        $departments = ['College of Engineering', 'College of Nursing', 'College of Business', 'College of Computer Studies'];

        $users = [];
        foreach ($definitions as $def) {
            $user = User::updateOrCreate(
                ['email' => $def['email']],
                [
                    'username' => $def['username'],
                    'password' => $password,
                    'must_change_password' => false,
                    'user_status_id' => $activeStatusId,
                ]
            );

            UserProfile::updateOrCreate(
                ['account_id' => $user->account_id],
                [
                    'first_name' => $def['first_name'],
                    'last_name' => $def['last_name'],
                    'student_id' => $def['student_id'],
                    'department' => $departments[array_rand($departments)],
                    'gender' => $def['gender'],
                    'phone' => '+639'.fake()->numerify('#########'),
                    'date_of_birth' => fake()->dateTimeBetween('-25 years', '-18 years')->format('Y-m-d'),
                    'address' => fake()->address(),
                ]
            );

            if ($studentRoleId) {
                UserRole::updateOrCreate(
                    ['account_id' => $user->account_id, 'role_id' => $studentRoleId],
                    ['assigned_date' => $today, 'is_active' => 1, 'assigned_by' => $user->account_id]
                );
            }

            $users[] = $user;
        }

        return $users;
    }

    // ─── Form Categories ─────────────────────────────────────────────────────

    /** @return array<string, FormCategory> */
    private function seedFormCategories(): array
    {
        $this->command->info('  → Form categories...');

        $rows = [
            ['name' => 'Academic Records', 'slug' => 'mock-academic-records'],
            ['name' => 'Financial Services', 'slug' => 'mock-financial-services'],
            ['name' => 'Student Affairs', 'slug' => 'mock-student-affairs'],
            ['name' => 'Administration', 'slug' => 'mock-administration'],
        ];

        $result = [];
        foreach ($rows as $row) {
            $result[$row['slug']] = FormCategory::firstOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name']]
            );
        }

        return $result;
    }

    // ─── Forms ───────────────────────────────────────────────────────────────

    // ─── Facilities ──────────────────────────────────────────────────────────

    private function seedFacilities(): void
    {
        $this->command->info('  → Facilities...');

        $facilities = [
            ['name' => 'Rm 101 — Main Building',      'description' => 'General-purpose classroom, capacity 40.'],
            ['name' => 'Rm 102 — Main Building',      'description' => 'General-purpose classroom, capacity 40.'],
            ['name' => 'Rm 201 — Main Building',      'description' => 'Lecture room with projector, capacity 60.'],
            ['name' => 'AVR — Audio-Visual Room',     'description' => 'AV-equipped room, capacity 80.'],
            ['name' => 'Bulwagang Aguinaldo',          'description' => 'Main auditorium, capacity 500.'],
            ['name' => 'Conference Room A',            'description' => 'Executive meeting room, capacity 20.'],
            ['name' => 'Conference Room B',            'description' => 'Meeting room, capacity 12.'],
            ['name' => 'Computer Lab 1',               'description' => 'IT laboratory with 40 workstations.'],
            ['name' => 'Computer Lab 2',               'description' => 'IT laboratory with 40 workstations.'],
            ['name' => 'Science Lab — Chemistry',      'description' => 'Chemistry laboratory, capacity 30.'],
            ['name' => 'Covered Court',                'description' => 'Multi-purpose sports facility.'],
            ['name' => 'Function Hall',                'description' => 'Large events venue, capacity 300.'],
        ];

        foreach ($facilities as $data) {
            Facility::firstOrCreate(['name' => $data['name']], [
                'description' => $data['description'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array<string, FormCategory>  $categories
     * @param  array<int, User>  $staffUsers
     * @return array<string, Form>
     */
    private function seedForms(array $categories, array $staffUsers): array
    {
        $this->command->info('  → Forms with fields...');

        $adminId = User::where('email', 'admin@auf.edu.ph')->value('account_id')
            ?? $staffUsers[0]->account_id;

        $publicPermId = \App\Modules\UserManagement\Models\Permission::where('slug', 'forms.public-access')->value('id');

        $forms = [];

        foreach ($this->formDefinitions($categories) as $def) {
            $form = Form::firstOrCreate(
                ['form_code' => $def['form_code']],
                [
                    'form_name' => $def['form_name'],
                    'form_family_code' => $def['form_code'],
                    'form_category_id' => $def['category_id'],
                    'description' => $def['description'],
                    'version' => 1,
                    'status' => $def['status'] ?? 'Inactive',
                    'is_locked' => $def['is_locked'] ?? false,
                    'email_notifications' => false,
                    'created_by' => $adminId,
                ]
            );

            if ($publicPermId) {
                $form->permissions()->syncWithoutDetaching([$publicPermId]);
            }

            if ($form->fields()->count() === 0) {
                foreach ($def['fields'] as $order => $fieldDef) {
                    FormField::create([
                        'form_id' => $form->id,
                        'field_name' => $fieldDef['field_name'],
                        'label' => $fieldDef['label'],
                        'data_type' => $fieldDef['data_type'],
                        'is_required' => $fieldDef['is_required'] ?? false,
                        'options' => $fieldDef['options'] ?? null,
                        'options_meta' => $fieldDef['options_meta'] ?? null,
                        'field_options' => $fieldDef['field_options'] ?? null,
                        'conditions' => $fieldDef['conditions'] ?? null,
                        'placeholder' => $fieldDef['placeholder'] ?? null,
                        'help_text' => $fieldDef['help_text'] ?? null,
                        'field_order' => $order + 1,
                        'date_mode' => $fieldDef['date_mode'] ?? null,
                        'use_slots' => $fieldDef['use_slots'] ?? false,
                        'require_facility' => $fieldDef['require_facility'] ?? false,
                    ]);
                }
            } else {
                // Sync advanced columns that may have been missing on first seeding.
                foreach ($def['fields'] as $order => $fieldDef) {
                    FormField::where('form_id', $form->id)
                        ->where('field_name', $fieldDef['field_name'])
                        ->update([
                            'options_meta' => isset($fieldDef['options_meta']) ? json_encode($fieldDef['options_meta']) : null,
                            'field_options' => isset($fieldDef['field_options']) ? json_encode($fieldDef['field_options']) : null,
                            'conditions' => isset($fieldDef['conditions']) ? json_encode($fieldDef['conditions']) : null,
                            'date_mode' => $fieldDef['date_mode'] ?? null,
                            'use_slots' => $fieldDef['use_slots'] ?? false,
                            'require_facility' => $fieldDef['require_facility'] ?? false,
                        ]);
                }
            }

            $forms[$def['form_code']] = $form->fresh(['fields']);
        }

        return $forms;
    }

    /** @return array<int, array<string, mixed>> */
    private function formDefinitions(array $categories): array
    {
        $acad = $categories['mock-academic-records']->id ?? null;
        $fin = $categories['mock-financial-services']->id ?? null;
        $sa = $categories['mock-student-affairs']->id ?? null;
        $adm = $categories['mock-administration']->id ?? null;

        return [
            // ── Active forms (will receive published workflows) ───────────

            [
                'form_code' => 'MOCK-COE-001',
                'form_name' => 'Certificate of Enrollment',
                'category_id' => $acad,
                'description' => 'Request for Certificate of Enrollment from the Registrar Office.',
                'fields' => [
                    ['field_name' => 'student_name', 'label' => 'Full Name', 'data_type' => 'text', 'is_required' => true, 'placeholder' => 'Enter full name'],
                    ['field_name' => 'student_id',   'label' => 'Student ID', 'data_type' => 'text', 'is_required' => true, 'placeholder' => '20XX-XXXXX'],
                    ['field_name' => 'purpose',       'label' => 'Purpose',    'data_type' => 'select', 'is_required' => true, 'options' => ['Scholarship', 'Employment', 'Transfer', 'Bank', 'Other']],
                ],
            ],

            [
                'form_code' => 'MOCK-TR-001',
                'form_name' => 'Official Transcript Request',
                'category_id' => $acad,
                'description' => 'Request for Official Transcript of Records.',
                'fields' => [
                    ['field_name' => 'full_name',      'label' => 'Full Name',                      'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'student_id',     'label' => 'Student ID',                     'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'course',         'label' => 'Course / Program',               'data_type' => 'select',   'is_required' => true, 'options' => ['BSCS', 'BSIT', 'BSN', 'BSBA', 'BSCE', 'BSME', 'Other']],
                    ['field_name' => 'year_graduated', 'label' => 'Year Graduated / Last Attended', 'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'copies_needed',  'label' => 'Number of Copies',               'data_type' => 'number',   'is_required' => true],
                    ['field_name' => 'purpose',        'label' => 'Purpose',                        'data_type' => 'select',   'is_required' => true, 'options' => ['Employment', 'Graduate School', 'Transfer', 'Board Exam', 'Other']],
                    ['field_name' => 'delivery_method', 'label' => 'Delivery Method',                'data_type' => 'radio',    'is_required' => true, 'options' => ['Pick-up', 'Mail Delivery', 'Email Soft Copy']],
                    ['field_name' => 'notes',          'label' => 'Additional Notes',               'data_type' => 'textarea', 'is_required' => false, 'placeholder' => 'Any special instructions...'],
                ],
            ],

            [
                'form_code' => 'MOCK-LOA-001',
                'form_name' => 'Leave of Absence Application',
                'category_id' => $sa,
                'description' => 'Application for Leave of Absence from the University.',
                'fields' => [
                    ['field_name' => 'student_name',  'label' => 'Student Name',           'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'student_id',    'label' => 'Student ID',             'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'leave_type',    'label' => 'Type of Leave',          'data_type' => 'select',   'is_required' => true, 'options' => ['Medical', 'Personal', 'Family Emergency', 'Financial', 'Military Service']],
                    ['field_name' => 'effective_date', 'label' => 'Effective Date',         'data_type' => 'date',     'is_required' => true],
                    ['field_name' => 'return_date',   'label' => 'Expected Return Date',   'data_type' => 'date',     'is_required' => false],
                    ['field_name' => 'reason',        'label' => 'Reason / Justification', 'data_type' => 'textarea', 'is_required' => true, 'placeholder' => 'Please explain the reason for leave...'],
                ],
            ],

            [
                'form_code' => 'MOCK-FAA-001',
                'form_name' => 'Financial Assistance Application',
                'category_id' => $fin,
                'description' => 'Application for financial assistance from the University.',
                'sensitive_fields' => ['family_income'],
                'fields' => [
                    ['field_name' => 'applicant_name',  'label' => 'Applicant Full Name',    'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'student_id',      'label' => 'Student ID',             'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'email',           'label' => 'Email Address',          'data_type' => 'email',    'is_required' => true],
                    ['field_name' => 'phone',           'label' => 'Contact Number',         'data_type' => 'text',     'is_required' => true, 'placeholder' => '+63-9XX-XXX-XXXX'],
                    ['field_name' => 'assistance_type', 'label' => 'Type of Assistance',     'data_type' => 'select',   'is_required' => true, 'options' => ['Tuition Discount', 'Emergency Loan', 'Book Allowance', 'Housing Allowance', 'Other']],
                    ['field_name' => 'amount_requested', 'label' => 'Amount Requested (PHP)', 'data_type' => 'number',   'is_required' => true],
                    ['field_name' => 'justification',   'label' => 'Justification',          'data_type' => 'textarea', 'is_required' => true],
                    ['field_name' => 'family_income',   'label' => 'Monthly Family Income',  'data_type' => 'select',   'is_required' => true, 'options' => ['Below 10,000', '10,000 - 20,000', '20,001 - 30,000', '30,001 - 50,000', 'Above 50,000']],
                    ['field_name' => 'supporting_docs', 'label' => 'Supporting Documents',   'data_type' => 'file',     'is_required' => false, 'help_text' => 'Upload ITR, certificate of indigency, or other supporting documents.'],
                    ['field_name' => 'agree_terms',     'label' => 'I agree to the terms and conditions of the financial assistance program', 'data_type' => 'checkbox', 'is_required' => true],
                ],
            ],

            [
                'form_code' => 'MOCK-EVT-001',
                'form_name' => 'Campus Event Registration',
                'category_id' => $sa,
                'description' => 'Registration for campus-organised events and activities.',
                'fields' => [
                    ['field_name' => 'participant_name',  'label' => 'Participant Name',   'data_type' => 'text',  'is_required' => true],
                    ['field_name' => 'student_id',        'label' => 'Student ID',         'data_type' => 'text',  'is_required' => true],
                    ['field_name' => 'event_name',        'label' => 'Event Name',         'data_type' => 'text',  'is_required' => true],
                    ['field_name' => 'participation_type', 'label' => 'Participation Type', 'data_type' => 'radio', 'is_required' => true, 'options' => ['Attendee', 'Volunteer', 'Speaker', 'Organizer']],
                ],
            ],

            [
                'form_code' => 'MOCK-LIB-001',
                'form_name' => 'Library Clearance Form',
                'category_id' => $adm,
                'description' => 'Request for library clearance certificate.',
                'fields' => [
                    ['field_name' => 'borrower_name', 'label' => 'Borrower Name',          'data_type' => 'text', 'is_required' => true],
                    ['field_name' => 'student_id',    'label' => 'Student / Employee ID',   'data_type' => 'text', 'is_required' => true],
                ],
            ],

            // ── Advanced active forms (exercise all FormField columns) ──────

            [
                // branch_condition workflow: amount_requested > 50000 → Step 2a; else Step 2b
                'form_code' => 'MOCK-RGA-001',
                'form_name' => 'Research Grant Application',
                'category_id' => $acad,
                'description' => 'Application for research funding from the University Research Office.',
                'sensitive_fields' => ['external_budget'],
                'fields' => [
                    [
                        'field_name' => 'full_name',
                        'label' => 'Principal Researcher',
                        'data_type' => 'text',
                        'is_required' => true,
                        'field_options' => ['auto_fill_name' => true],
                        'placeholder' => 'Your full name',
                    ],
                    [
                        'field_name' => 'department',
                        'label' => 'Department / College',
                        'data_type' => 'text',
                        'is_required' => true,
                        'placeholder' => 'e.g. College of Computer Studies',
                    ],
                    [
                        'field_name' => 'grant_type',
                        'label' => 'Grant Type',
                        'data_type' => 'select',
                        'is_required' => true,
                        'options' => ['Internal', 'External'],
                        // options_meta: Internal requires a text note (supervisor);
                        // External requires a qty (number of partner institutions).
                        'options_meta' => [
                            ['label' => 'Internal', 'value' => 'Internal', 'requires_text' => true, 'text_label' => 'Supervising Faculty Name'],
                            ['label' => 'External', 'value' => 'External', 'requires_qty' => true, 'qty_label' => 'Partner Institutions', 'min_qty' => 1, 'max_qty' => 10, 'default_qty' => 1, 'step' => 1, 'unit' => 'partners'],
                        ],
                    ],
                    [
                        'field_name' => 'department_supervisor',
                        'label' => 'Department Supervisor',
                        'data_type' => 'text',
                        'is_required' => false,
                        'placeholder' => 'Name of supervising faculty',
                        // Visible only when grant_type == 'Internal'
                        'conditions' => [
                            ['field_name' => 'grant_type', 'operator' => 'equals', 'value' => 'Internal', 'action' => 'show'],
                        ],
                    ],
                    [
                        'field_name' => 'external_partner',
                        'label' => 'External Partner Organisation',
                        'data_type' => 'text',
                        'is_required' => false,
                        'placeholder' => 'Name of external partner',
                        // Visible only when grant_type == 'External'
                        'conditions' => [
                            ['field_name' => 'grant_type', 'operator' => 'equals', 'value' => 'External', 'action' => 'show'],
                        ],
                    ],
                    [
                        'field_name' => 'external_budget',
                        'label' => 'External Funding Amount (PHP)',
                        'data_type' => 'number',
                        'is_required' => false,
                        'placeholder' => 'Amount committed by external partner',
                        'conditions' => [
                            ['field_name' => 'grant_type', 'operator' => 'equals', 'value' => 'External', 'action' => 'show'],
                        ],
                    ],
                    [
                        'field_name' => 'research_title',
                        'label' => 'Research Title',
                        'data_type' => 'text',
                        'is_required' => true,
                        'placeholder' => 'Full title of the research project',
                    ],
                    [
                        'field_name' => 'amount_requested',
                        'label' => 'Amount Requested (PHP)',
                        'data_type' => 'number',
                        'is_required' => true,
                        'placeholder' => 'e.g. 75000',
                        'help_text' => 'Requests above PHP 50,000 require Finance high-value review.',
                    ],
                    [
                        'field_name' => 'project_duration_months',
                        'label' => 'Project Duration (months)',
                        'data_type' => 'number',
                        'is_required' => true,
                        'placeholder' => '6',
                    ],
                    [
                        'field_name' => 'supporting_documents',
                        'label' => 'Supporting Documents',
                        'data_type' => 'file',
                        'is_required' => false,
                        'help_text' => 'Upload research proposal, CV, or endorsement letter.',
                    ],
                ],
            ],

            [
                // Multi-OR approver workflow + table field + section / heading layout
                'form_code' => 'MOCK-EQR-001',
                'form_name' => 'Equipment Requisition Form',
                'category_id' => $adm,
                'description' => 'Request for equipment procurement through the university.',
                'fields' => [
                    [
                        'field_name' => 'requester_section',
                        'label' => 'Requester Information',
                        'data_type' => 'section',
                        'is_required' => false,
                        'field_options' => [
                            'section_title' => 'Requester Information',
                            'section_description' => 'Please provide your contact details.',
                        ],
                    ],
                    [
                        'field_name' => 'requester_name',
                        'label' => 'Requester Name',
                        'data_type' => 'text',
                        'is_required' => true,
                        'field_options' => ['auto_fill_name' => true],
                        'placeholder' => 'Your full name',
                    ],
                    [
                        'field_name' => 'department',
                        'label' => 'Department / Office',
                        'data_type' => 'text',
                        'is_required' => true,
                    ],
                    [
                        'field_name' => 'items_heading',
                        'label' => 'Equipment Items',
                        'data_type' => 'heading',
                        'is_required' => false,
                        'field_options' => [
                            'heading_content' => 'Equipment Items',
                            'heading_size' => 'medium',
                        ],
                    ],
                    [
                        'field_name' => 'items',
                        'label' => 'Items Requested',
                        'data_type' => 'table',
                        'is_required' => true,
                        'help_text' => 'Add one row per item. Quantity is required.',
                        'field_options' => [
                            'table_columns' => [
                                ['id' => 'item_name',  'label' => 'Item Name',        'type' => 'text',    'required' => true],
                                ['id' => 'quantity',   'label' => 'Quantity',         'type' => 'number',  'required' => true],
                                ['id' => 'unit_price', 'label' => 'Unit Price (PHP)', 'type' => 'number',  'required' => false],
                                ['id' => 'specs',      'label' => 'Specifications',   'type' => 'textarea', 'required' => false],
                            ],
                            'min_rows' => 1,
                            'max_rows' => 10,
                        ],
                    ],
                    [
                        'field_name' => 'priority',
                        'label' => 'Priority Level',
                        'data_type' => 'select',
                        'is_required' => true,
                        'options' => ['Normal', 'Urgent', 'Emergency'],
                    ],
                    [
                        'field_name' => 'urgency_justification',
                        'label' => 'Urgency Justification',
                        'data_type' => 'textarea',
                        'is_required' => false,
                        'placeholder' => 'Explain why this request is urgent or an emergency.',
                        // Visible only when priority is NOT Normal
                        'conditions' => [
                            ['field_name' => 'priority', 'operator' => 'not_equals', 'value' => 'Normal', 'action' => 'show'],
                        ],
                    ],
                    [
                        'field_name' => 'delivery_heading',
                        'label' => 'Delivery Details',
                        'data_type' => 'heading',
                        'is_required' => false,
                        'field_options' => [
                            'heading_content' => 'Delivery & Timeline',
                            'heading_size' => 'small',
                        ],
                    ],
                    [
                        'field_name' => 'needed_by_date',
                        'label' => 'Needed By',
                        'data_type' => 'date',
                        'is_required' => true,
                        'help_text' => 'Earliest acceptable delivery date.',
                    ],
                    [
                        'field_name' => 'delivery_location',
                        'label' => 'Delivery Location',
                        'data_type' => 'text',
                        'is_required' => true,
                        'placeholder' => 'Building and room number',
                    ],
                ],
            ],

            [
                // watch_fields workflow step + date range + phone field + conditional visibility
                'form_code' => 'MOCK-FBR-001',
                'form_name' => 'Facility Booking Request',
                'category_id' => $sa,
                'description' => 'Request to book a university facility for an event or activity.',
                'fields' => [
                    [
                        'field_name' => 'intro_heading',
                        'label' => 'Facility Booking Request',
                        'data_type' => 'heading',
                        'is_required' => false,
                        'field_options' => [
                            'heading_content' => 'Facility Booking Request',
                            'heading_size' => 'large',
                        ],
                    ],
                    [
                        'field_name' => 'requester_name',
                        'label' => 'Requester Name',
                        'data_type' => 'text',
                        'is_required' => true,
                        'field_options' => ['auto_fill_name' => true],
                        'placeholder' => 'Your full name',
                    ],
                    [
                        'field_name' => 'student_id',
                        'label' => 'Student / Employee ID',
                        'data_type' => 'text',
                        'is_required' => true,
                        'placeholder' => '20XX-XXXXX',
                    ],
                    [
                        'field_name' => 'contact_number',
                        'label' => 'Contact Number',
                        'data_type' => 'phone',
                        'is_required' => true,
                        'placeholder' => '+63-9XX-XXX-XXXX',
                    ],
                    [
                        'field_name' => 'facility_type',
                        'label' => 'Facility Type',
                        'data_type' => 'select',
                        'is_required' => true,
                        'options' => ['Classroom', 'Auditorium', 'Meeting Room', 'Laboratory', 'Sports Facility'],
                    ],
                    [
                        'field_name' => 'booking_date',
                        'label' => 'Booking Date',
                        'data_type' => 'date',
                        'is_required' => true,
                        // use_slots forces date_mode = 'single' (StoreFormRequest normalization rule).
                        // require_facility is valid only when use_slots is true.
                        // The student selects a single date; available time slots for that
                        // date + facility are returned by the slots API and stored in tbl_slots.
                        'date_mode' => 'single',
                        'use_slots' => true,
                        'require_facility' => true,
                        'help_text' => 'Select the date and time slot for the booking.',
                    ],
                    [
                        'field_name' => 'purpose',
                        'label' => 'Purpose of Booking',
                        'data_type' => 'textarea',
                        'is_required' => true,
                        'placeholder' => 'Describe the activity or event.',
                    ],
                    [
                        'field_name' => 'expected_attendees',
                        'label' => 'Expected Attendees',
                        'data_type' => 'number',
                        'is_required' => true,
                        'placeholder' => 'Number of attendees',
                    ],
                    [
                        'field_name' => 'has_equipment_needs',
                        'label' => 'Does this event require equipment setup?',
                        'data_type' => 'radio',
                        'is_required' => true,
                        'options' => ['Yes', 'No'],
                    ],
                    [
                        'field_name' => 'equipment_details',
                        'label' => 'Equipment Details',
                        'data_type' => 'textarea',
                        'is_required' => false,
                        'placeholder' => 'List the equipment needed (projector, sound system, chairs, etc.)',
                        // Visible only when has_equipment_needs == 'Yes'
                        'conditions' => [
                            ['field_name' => 'has_equipment_needs', 'operator' => 'equals', 'value' => 'Yes', 'action' => 'show'],
                        ],
                    ],
                    [
                        // Step 3 of the workflow uses watch_fields: ['supporting_documents']
                        // → Admin Document Verification is Skipped when this field is null
                        'field_name' => 'supporting_documents',
                        'label' => 'Supporting Documents',
                        'data_type' => 'file',
                        'is_required' => false,
                        'help_text' => 'Upload event proposal, college approval, or endorsement letter.',
                    ],
                ],
            ],

            // ── Phase 3: options_meta (qty + text) and date-range forms ──────

            [
                // options_meta: checkbox with requires_qty; radio with requires_text
                'form_code' => 'MOCK-ECO-001',
                'form_name' => 'Event Catering Order',
                'category_id' => $sa,
                'description' => 'Order food and beverage packages for an approved campus event.',
                'fields' => [
                    [
                        'field_name' => 'requester_name',
                        'label' => 'Requester Name',
                        'data_type' => 'text',
                        'is_required' => true,
                        'field_options' => ['auto_fill_name' => true],
                        'placeholder' => 'Your full name',
                    ],
                    [
                        'field_name' => 'event_name',
                        'label' => 'Event Name',
                        'data_type' => 'text',
                        'is_required' => true,
                        'placeholder' => 'e.g. Department Recognition Day',
                    ],
                    [
                        'field_name' => 'event_date',
                        'label' => 'Event Date',
                        'data_type' => 'date',
                        'is_required' => true,
                        'date_mode' => 'single',
                        'help_text' => 'Date the catering is needed.',
                    ],
                    [
                        'field_name' => 'expected_guests',
                        'label' => 'Expected Number of Guests',
                        'data_type' => 'number',
                        'is_required' => true,
                        'placeholder' => '50',
                    ],
                    [
                        // Checkbox — each option has requires_qty so the submitter
                        // specifies how many servings/boxes of each item they need.
                        // options_meta shape: { label, value, requires_qty, qty_label,
                        //   min_qty, max_qty, step, default_qty, unit,
                        //   requires_text, text_label }
                        // (mirrors the normalised shape from StoreFormRequest::prepareForValidation)
                        'field_name' => 'food_choices',
                        'label' => 'Food Package Selection',
                        'data_type' => 'checkbox',
                        'is_required' => true,
                        'help_text' => 'Select one or more packages and enter the quantity needed.',
                        'options' => [
                            'Chicken Rice & Vegetable',
                            'Pasta Buffet',
                            'Packed Snack Boxes',
                            'Beverages Package',
                        ],
                        'options_meta' => [
                            [
                                'label' => 'Chicken Rice & Vegetable',
                                'value' => 'chicken_rice',
                                'requires_qty' => true,
                                'qty_label' => 'Servings',
                                'min_qty' => 5,
                                'max_qty' => 200,
                                'step' => 5,
                                'default_qty' => 20,
                                'unit' => 'pax',
                                'requires_text' => false,
                                'text_label' => 'Specify',
                            ],
                            [
                                'label' => 'Pasta Buffet',
                                'value' => 'pasta_buffet',
                                'requires_qty' => true,
                                'qty_label' => 'Servings',
                                'min_qty' => 5,
                                'max_qty' => 200,
                                'step' => 5,
                                'default_qty' => 20,
                                'unit' => 'pax',
                                'requires_text' => false,
                                'text_label' => 'Specify',
                            ],
                            [
                                'label' => 'Packed Snack Boxes',
                                'value' => 'packed_snacks',
                                'requires_qty' => true,
                                'qty_label' => 'Boxes',
                                'min_qty' => 10,
                                'max_qty' => 300,
                                'step' => 10,
                                'default_qty' => 30,
                                'unit' => 'boxes',
                                'requires_text' => false,
                                'text_label' => 'Specify',
                            ],
                            [
                                'label' => 'Beverages Package',
                                'value' => 'beverages',
                                'requires_qty' => true,
                                'qty_label' => 'Sets',
                                'min_qty' => 1,
                                'max_qty' => 50,
                                'step' => 1,
                                'default_qty' => 5,
                                'unit' => 'sets',
                                'requires_text' => false,
                                'text_label' => 'Specify',
                            ],
                        ],
                    ],
                    [
                        // Radio — most options are plain; the last option requires a text
                        // input so the submitter can write a custom dietary requirement.
                        // options_meta shape is the same normalised structure.
                        'field_name' => 'dietary_preference',
                        'label' => 'Dietary Preference',
                        'data_type' => 'radio',
                        'is_required' => true,
                        'options' => ['No Restrictions', 'Halal', 'Vegetarian', 'Other'],
                        'options_meta' => [
                            [
                                'label' => 'No Restrictions',
                                'value' => 'no_restrictions',
                                'requires_qty' => false,
                                'qty_label' => 'Qty',
                                'min_qty' => 0,
                                'max_qty' => null,
                                'step' => 1,
                                'default_qty' => 1,
                                'unit' => 'pcs',
                                'requires_text' => false,
                                'text_label' => 'Specify',
                            ],
                            [
                                'label' => 'Halal',
                                'value' => 'halal',
                                'requires_qty' => false,
                                'qty_label' => 'Qty',
                                'min_qty' => 0,
                                'max_qty' => null,
                                'step' => 1,
                                'default_qty' => 1,
                                'unit' => 'pcs',
                                'requires_text' => false,
                                'text_label' => 'Specify',
                            ],
                            [
                                'label' => 'Vegetarian',
                                'value' => 'vegetarian',
                                'requires_qty' => false,
                                'qty_label' => 'Qty',
                                'min_qty' => 0,
                                'max_qty' => null,
                                'step' => 1,
                                'default_qty' => 1,
                                'unit' => 'pcs',
                                'requires_text' => false,
                                'text_label' => 'Specify',
                            ],
                            [
                                // "Other" triggers a text input for the custom requirement.
                                'label' => 'Other',
                                'value' => 'other',
                                'requires_qty' => false,
                                'qty_label' => 'Qty',
                                'min_qty' => 0,
                                'max_qty' => null,
                                'step' => 1,
                                'default_qty' => 1,
                                'unit' => 'pcs',
                                'requires_text' => true,
                                'text_label' => 'Specify dietary requirement',
                            ],
                        ],
                    ],
                    [
                        'field_name' => 'special_instructions',
                        'label' => 'Special Instructions',
                        'data_type' => 'textarea',
                        'is_required' => false,
                        'placeholder' => 'Any setup, serving, or allergy notes for the caterer.',
                    ],
                ],
            ],

            [
                // date_mode: 'range' — the submission stores {start: 'Y-m-d', end: 'Y-m-d'}
                'form_code' => 'MOCK-SLA-001',
                'form_name' => 'Study Leave Application',
                'category_id' => $acad,
                'description' => 'Application for study leave (sabbatical / research / training) for faculty and staff.',
                'fields' => [
                    [
                        'field_name' => 'full_name',
                        'label' => 'Full Name',
                        'data_type' => 'text',
                        'is_required' => true,
                        'field_options' => ['auto_fill_name' => true],
                        'placeholder' => 'Your full name',
                    ],
                    [
                        'field_name' => 'employee_id',
                        'label' => 'Employee ID',
                        'data_type' => 'text',
                        'is_required' => true,
                        'placeholder' => 'e.g. EMP-0042',
                    ],
                    [
                        'field_name' => 'department',
                        'label' => 'Department / College',
                        'data_type' => 'text',
                        'is_required' => true,
                    ],
                    [
                        // Radio with options_meta: most options are plain; "Other" triggers
                        // a free-text input so the applicant can name an unlisted leave type.
                        'field_name' => 'leave_type',
                        'label' => 'Type of Leave',
                        'data_type' => 'radio',
                        'is_required' => true,
                        'options' => ['Sick Leave', 'Vacation Leave', 'Study / Sabbatical Leave', 'Special Privilege Leave', 'Other'],
                        'options_meta' => [
                            [
                                'label' => 'Sick Leave',          'value' => 'sick_leave',
                                'requires_qty' => false, 'qty_label' => 'Qty', 'min_qty' => 0, 'max_qty' => null, 'step' => 1, 'default_qty' => 1, 'unit' => 'pcs',
                                'requires_text' => false, 'text_label' => 'Specify',
                            ],
                            [
                                'label' => 'Vacation Leave',      'value' => 'vacation_leave',
                                'requires_qty' => false, 'qty_label' => 'Qty', 'min_qty' => 0, 'max_qty' => null, 'step' => 1, 'default_qty' => 1, 'unit' => 'pcs',
                                'requires_text' => false, 'text_label' => 'Specify',
                            ],
                            [
                                'label' => 'Study / Sabbatical Leave', 'value' => 'study_leave',
                                'requires_qty' => false, 'qty_label' => 'Qty', 'min_qty' => 0, 'max_qty' => null, 'step' => 1, 'default_qty' => 1, 'unit' => 'pcs',
                                'requires_text' => false, 'text_label' => 'Specify',
                            ],
                            [
                                'label' => 'Special Privilege Leave', 'value' => 'special_privilege',
                                'requires_qty' => false, 'qty_label' => 'Qty', 'min_qty' => 0, 'max_qty' => null, 'step' => 1, 'default_qty' => 1, 'unit' => 'pcs',
                                'requires_text' => false, 'text_label' => 'Specify',
                            ],
                            [
                                // "Other" triggers a text input for the custom leave type.
                                'label' => 'Other', 'value' => 'other',
                                'requires_qty' => false, 'qty_label' => 'Qty', 'min_qty' => 0, 'max_qty' => null, 'step' => 1, 'default_qty' => 1, 'unit' => 'pcs',
                                'requires_text' => true, 'text_label' => 'Specify leave type',
                            ],
                        ],
                    ],
                    [
                        // Date range: submission payload stores {start: 'Y-m-d', end: 'Y-m-d'}.
                        // use_slots must be false (StoreFormRequest normalisation forces
                        // date_mode to 'single' when use_slots is true — they are mutually
                        // exclusive).
                        'field_name' => 'leave_period',
                        'label' => 'Leave Period',
                        'data_type' => 'date',
                        'is_required' => true,
                        'date_mode' => 'range',
                        'use_slots' => false,
                        'require_facility' => false,
                        'help_text' => 'Select the start and end date of the requested leave.',
                    ],
                    [
                        'field_name' => 'reason',
                        'label' => 'Reason / Purpose',
                        'data_type' => 'textarea',
                        'is_required' => true,
                        'placeholder' => 'Describe the purpose or justification for this leave.',
                    ],
                    [
                        'field_name' => 'supporting_documents',
                        'label' => 'Supporting Documents',
                        'data_type' => 'file',
                        'is_required' => false,
                        'help_text' => 'Upload medical certificate, training invitation, or other supporting documents.',
                    ],
                ],
            ],

            // ── Draft / Inactive forms (no workflows, not renderable) ─────

            [
                'form_code' => 'MOCK-OVLD-001',
                'form_name' => 'Overload Permission Form',
                'category_id' => $acad,
                'description' => 'Request for permission to take overload units. (Draft — not yet active)',
                'status' => 'Inactive',
                'is_locked' => false,
                'fields' => [
                    ['field_name' => 'student_name',    'label' => 'Student Name',                 'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'student_id',      'label' => 'Student ID',                   'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'current_units',   'label' => 'Currently Enrolled Units',     'data_type' => 'number',   'is_required' => true],
                    ['field_name' => 'requested_units', 'label' => 'Units to Add',                 'data_type' => 'number',   'is_required' => true],
                    ['field_name' => 'reason',          'label' => 'Reason for Overload',          'data_type' => 'textarea', 'is_required' => true],
                ],
            ],

            [
                'form_code' => 'MOCK-GMC-001',
                'form_name' => 'Good Moral Certificate',
                'category_id' => $adm,
                'description' => 'Request for Good Moral Certificate from Student Affairs. (Draft — not yet active)',
                'status' => 'Inactive',
                'is_locked' => false,
                'fields' => [
                    ['field_name' => 'student_name', 'label' => 'Full Name',   'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'student_id',   'label' => 'Student ID',  'data_type' => 'text',     'is_required' => true],
                    ['field_name' => 'purpose',      'label' => 'Purpose',     'data_type' => 'textarea', 'is_required' => true, 'placeholder' => 'Please state the purpose...'],
                ],
            ],
        ];
    }

    // ─── Workflows ───────────────────────────────────────────────────────────

    /**
     * @param  array<string, Form>  $forms
     * @param  array<int, User>  $staffUsers
     * @return array{0: array<string, Workflow>, 1: array<string, WorkflowVersion>}
     */
    private function seedWorkflows(array $forms, array $staffUsers): array
    {
        $this->command->info('  → Workflows (create + publish)...');

        $adminId = User::where('email', 'admin@auf.edu.ph')->value('account_id')
            ?? $staffUsers[0]->account_id;

        $byEmail = [];
        foreach ($staffUsers as $u) {
            $byEmail[$u->email] = $u;
        }

        $registrar = $byEmail['auflow.auf@gmail.com'];
        $itAdmin = $byEmail['kevsmiranda07@gmail.com'];
        $adminOfficer = $byEmail['kevsmiranda08@gmail.com'];
        $financeOfficer = $byEmail['kevsmir02@gmail.com'];
        $studentAffairs = $byEmail['miranda.kevin@student.auf.edu.ph'];

        $workflowService = app(WorkflowService::class);

        $definitions = [

            // 1. Sequential 2-step — COE
            'MOCK-COE-001' => [
                'name' => 'COE Approval Flow',
                'type' => 'Sequential',
                'steps' => [
                    ['step_name' => 'Registrar Review',  'step_order' => 1, 'step_group' => 1, 'approvers' => [['account_id' => $registrar->account_id,    'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Admin Approval',    'step_order' => 2, 'step_group' => 2, 'approvers' => [['account_id' => $adminOfficer->account_id, 'condition' => 'primary', 'order' => 0]]],
                ],
            ],

            // 2. Sequential 3-step — Transcript
            'MOCK-TR-001' => [
                'name' => 'Transcript Request Flow',
                'type' => 'Sequential',
                'steps' => [
                    ['step_name' => 'Registrar Verification', 'step_order' => 1, 'step_group' => 1, 'approvers' => [['account_id' => $registrar->account_id,    'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Records Processing',     'step_order' => 2, 'step_group' => 2, 'approvers' => [['account_id' => $itAdmin->account_id,      'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Final Sign-off',         'step_order' => 3, 'step_group' => 3, 'approvers' => [['account_id' => $registrar->account_id,    'condition' => 'primary', 'order' => 0]]],
                ],
            ],

            // 3. Sequential 2-step — Leave of Absence
            'MOCK-LOA-001' => [
                'name' => 'Leave of Absence Approval',
                'type' => 'Sequential',
                'steps' => [
                    ['step_name' => 'Student Affairs Review', 'step_order' => 1, 'step_group' => 1, 'approvers' => [['account_id' => $studentAffairs->account_id, 'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Dean Approval',          'step_order' => 2, 'step_group' => 2, 'approvers' => [['account_id' => $adminOfficer->account_id,   'condition' => 'primary', 'order' => 0]]],
                ],
            ],

            // 4. Parallel (1 → 2a+2b → 3) — Financial Assistance
            'MOCK-FAA-001' => [
                'name' => 'Financial Assistance Review',
                'type' => 'Parallel',
                'steps' => [
                    ['step_name' => 'Initial Screening',      'step_order' => 1, 'step_group' => 1, 'approvers' => [['account_id' => $studentAffairs->account_id, 'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Finance Evaluation',     'step_order' => 2, 'step_group' => 2, 'approvers' => [['account_id' => $financeOfficer->account_id, 'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Academic Standing Check', 'step_order' => 3, 'step_group' => 2, 'approvers' => [['account_id' => $registrar->account_id,      'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Final Approval',         'step_order' => 4, 'step_group' => 3, 'approvers' => [
                        ['account_id' => $adminOfficer->account_id,   'condition' => 'primary', 'order' => 0],
                        ['account_id' => $financeOfficer->account_id, 'condition' => 'or',      'order' => 1],
                    ]],
                ],
            ],

            // 5. Mixed (1 → 2a+2b → 3) — Campus Event
            'MOCK-EVT-001' => [
                'name' => 'Event Registration Approval',
                'type' => 'Parallel',
                'steps' => [
                    ['step_name' => 'Registration Screening', 'step_order' => 1, 'step_group' => 1, 'approvers' => [['account_id' => $studentAffairs->account_id, 'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'IT Clearance',           'step_order' => 2, 'step_group' => 2, 'approvers' => [['account_id' => $itAdmin->account_id,        'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Admin Clearance',        'step_order' => 3, 'step_group' => 2, 'approvers' => [['account_id' => $adminOfficer->account_id,   'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Final Confirmation',     'step_order' => 4, 'step_group' => 3, 'approvers' => [['account_id' => $studentAffairs->account_id, 'condition' => 'primary', 'order' => 0]]],
                ],
            ],

            // 6. Sequential 3-step — Library Clearance
            'MOCK-LIB-001' => [
                'name' => 'Library Clearance Processing',
                'type' => 'Sequential',
                'steps' => [
                    ['step_name' => 'Librarian Check',    'step_order' => 1, 'step_group' => 1, 'approvers' => [['account_id' => $itAdmin->account_id,      'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Admin Verification', 'step_order' => 2, 'step_group' => 2, 'approvers' => [['account_id' => $adminOfficer->account_id, 'condition' => 'primary', 'order' => 0]]],
                    ['step_name' => 'Final Issuance',     'step_order' => 3, 'step_group' => 3, 'approvers' => [['account_id' => $itAdmin->account_id,      'condition' => 'primary', 'order' => 0]]],
                ],
            ],

            // ── Advanced workflows: branch_condition, multi-OR approvers, watch_fields ──

            // 7. Conditional branching — Research Grant Application
            //    Group 2 has TWO mutually exclusive steps (complementary branch_conditions).
            //    Step 2a runs only if amount_requested > 50000 (Finance High-Value).
            //    Step 2b runs only if amount_requested <= 50000 (Finance Standard).
            //    WorkflowConditionEvaluator::evaluate() is what the runtime calls;
            //    our shouldSkipStep() replicates that logic so seeded progress rows match.
            'MOCK-RGA-001' => [
                'name' => 'Research Grant Approval — Conditional Branch',
                'type' => 'Parallel',
                'steps' => [
                    [
                        'step_name' => 'Research Office Initial Review',
                        'step_order' => 1, 'step_group' => 1,
                        'max_duration_hours' => 72,
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                        ],
                        'approvers' => [['account_id' => $registrar->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                    [
                        'step_name' => 'Finance High-Value Review (>50 000)',
                        'step_order' => 2, 'step_group' => 2,
                        'max_duration_hours' => 48,
                        // Skipped when amount_requested <= 50000
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                            'branch_condition' => ['field' => 'amount_requested', 'operator' => '>', 'value' => 50000],
                        ],
                        'approvers' => [['account_id' => $financeOfficer->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                    [
                        'step_name' => 'Finance Standard Review (<=50 000)',
                        'step_order' => 3, 'step_group' => 2,
                        'max_duration_hours' => 48,
                        // Skipped when amount_requested > 50000
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                            'branch_condition' => ['field' => 'amount_requested', 'operator' => '<=', 'value' => 50000],
                        ],
                        'approvers' => [['account_id' => $studentAffairs->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                    [
                        'step_name' => 'VP / Admin Final Approval',
                        'step_order' => 4, 'step_group' => 3,
                        'max_duration_hours' => 72,
                        // Custom 24-hour reminder; any of adminOfficer OR registrar can approve
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'custom', 'reminder_interval' => 'custom',
                            'reminder_value' => 24, 'reminder_unit' => 'hours',
                        ],
                        'approvers' => [
                            ['account_id' => $adminOfficer->account_id, 'condition' => 'primary', 'order' => 0],
                            ['account_id' => $registrar->account_id,    'condition' => 'or',      'order' => 1],
                        ],
                    ],
                ],
            ],

            // 8. Multi-OR approvers + custom SLA reminders — Equipment Requisition
            //    Step 1: adminOfficer OR itAdmin can approve (or condition).
            //    Step 3: registrar OR itAdmin can sign off (or condition).
            //    NOTE: The runtime creates ONE progress row per step; the 'or' condition
            //    means any listed approver is authorised to act on that row (isAuthorizedToAct).
            //    There is no "all approvers must approve" mode in the current engine.
            'MOCK-EQR-001' => [
                'name' => 'Equipment Requisition — Multi-Approver',
                'type' => 'Sequential',
                'steps' => [
                    [
                        'step_name' => 'Dept Head / IT Review',
                        'step_order' => 1, 'step_group' => 1,
                        'max_duration_hours' => 48,
                        // Custom 24-hour reminder
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'custom', 'reminder_interval' => 'custom',
                            'reminder_value' => 24, 'reminder_unit' => 'hours',
                        ],
                        // ANY ONE of adminOfficer or itAdmin can approve
                        'approvers' => [
                            ['account_id' => $adminOfficer->account_id, 'condition' => 'primary', 'order' => 0],
                            ['account_id' => $itAdmin->account_id,      'condition' => 'or',      'order' => 1],
                        ],
                    ],
                    [
                        'step_name' => 'Procurement Verification',
                        'step_order' => 2, 'step_group' => 2,
                        'max_duration_hours' => 24,
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                        ],
                        'approvers' => [['account_id' => $financeOfficer->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                    [
                        'step_name' => 'Registrar / IT Final Sign-off',
                        'step_order' => 3, 'step_group' => 3,
                        'max_duration_hours' => 48,
                        // Custom 12-hour reminder
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'custom', 'reminder_interval' => 'custom',
                            'reminder_value' => 12, 'reminder_unit' => 'hours',
                        ],
                        // ANY ONE of registrar or itAdmin can sign off
                        'approvers' => [
                            ['account_id' => $registrar->account_id, 'condition' => 'primary', 'order' => 0],
                            ['account_id' => $itAdmin->account_id,   'condition' => 'or',      'order' => 1],
                        ],
                    ],
                ],
            ],

            // 9. watch_fields step + custom SLA — Facility Booking Request
            //    Step 3 is SKIPPED when supporting_documents is empty (watch_fields: ['supporting_documents']).
            //    ~30 % of seeded FBR submissions carry a non-null supporting_documents value
            //    (buildSamplePayload returns a fake path 30 % of the time for file fields),
            //    so ~30 % of FBR progress rows will have Step 3 as Pending/Approved.
            'MOCK-FBR-001' => [
                'name' => 'Facility Booking — Watch-Fields',
                'type' => 'Sequential',
                'steps' => [
                    [
                        'step_name' => 'Facility Availability Check',
                        'step_order' => 1, 'step_group' => 1,
                        'max_duration_hours' => 24,
                        // Custom 12-hour reminder
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'custom', 'reminder_interval' => 'custom',
                            'reminder_value' => 12, 'reminder_unit' => 'hours',
                        ],
                        'approvers' => [['account_id' => $itAdmin->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                    [
                        'step_name' => 'Student Affairs Review',
                        'step_order' => 2, 'step_group' => 2,
                        'max_duration_hours' => 48,
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                        ],
                        'approvers' => [['account_id' => $studentAffairs->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                    [
                        'step_name' => 'Admin Document Verification',
                        'step_order' => 3, 'step_group' => 3,
                        'max_duration_hours' => 24,
                        // watch_fields: Skipped when supporting_documents is empty
                        'step_conditions' => [
                            'type' => 'approval',
                            'watch_fields' => ['supporting_documents'],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                        ],
                        'approvers' => [['account_id' => $adminOfficer->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                ],
            ],

            // ── Phase 3 workflows ─────────────────────────────────────────

            // 10. options_meta (checkbox qty + radio text) — Event Catering Order
            'MOCK-ECO-001' => [
                'name' => 'Event Catering Review',
                'type' => 'Sequential',
                'steps' => [
                    [
                        'step_name' => 'Student Affairs Review',
                        'step_order' => 1, 'step_group' => 1,
                        'max_duration_hours' => 48,
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                        ],
                        'approvers' => [['account_id' => $studentAffairs->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                    [
                        'step_name' => 'Admin Final Approval',
                        'step_order' => 2, 'step_group' => 2,
                        'max_duration_hours' => 24,
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                        ],
                        'approvers' => [['account_id' => $adminOfficer->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                ],
            ],

            // 11. date range — Study Leave Application
            'MOCK-SLA-001' => [
                'name' => 'Study Leave Approval',
                'type' => 'Sequential',
                'steps' => [
                    [
                        'step_name' => 'Department Head Review',
                        'step_order' => 1, 'step_group' => 1,
                        'max_duration_hours' => 72,
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                        ],
                        'approvers' => [['account_id' => $registrar->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                    [
                        'step_name' => 'HR / Admin Endorsement',
                        'step_order' => 2, 'step_group' => 2,
                        'max_duration_hours' => 72,
                        'step_conditions' => [
                            'type' => 'approval', 'watch_fields' => [],
                            'reminder_mode' => 'default', 'reminder_interval' => 'default',
                        ],
                        'approvers' => [['account_id' => $adminOfficer->account_id, 'condition' => 'primary', 'order' => 0]],
                    ],
                ],
            ],
        ];

        $workflows = [];
        $versions = [];

        foreach ($definitions as $formCode => $def) {
            $form = $forms[$formCode] ?? null;
            if (! $form) {
                continue;
            }

            // Idempotent: reuse existing active workflow + version
            $existing = Workflow::where('form_id', $form->id)->where('status', 'Active')->first();
            if ($existing) {
                $existingVersion = WorkflowVersion::where('workflow_id', $existing->id)
                    ->where('is_current', true)
                    ->first();

                if ($existingVersion) {
                    $workflows[$formCode] = $existing;
                    $versions[$formCode] = $existingVersion;
                    $this->command->line("    skip {$formCode} (already active)");

                    continue;
                }
            }

            DB::transaction(function () use ($def, $form, $adminId, $formCode, $workflowService, &$workflows, &$versions) {
                Workflow::where('form_id', $form->id)->where('status', 'Draft')->delete();

                $workflow = Workflow::create([
                    'workflow_name' => $def['name'],
                    'workflow_type' => $def['type'],
                    'form_id' => $form->id,
                    'status' => 'Draft',
                    'created_by' => $adminId,
                    'workflow_settings' => ['nodes' => [], 'edges' => []],
                ]);

                foreach ($def['steps'] as $stepDef) {
                    $primaryApprover = $stepDef['approvers'][0]['account_id'];
                    $maxDuration = $stepDef['max_duration_hours'] ?? 48;
                    // Use per-step step_conditions when provided; otherwise use a sensible default.
                    $stepConditions = $stepDef['step_conditions'] ?? [
                        'type' => 'approval',
                        'watch_fields' => [],
                        'reminder_mode' => 'default',
                        'reminder_interval' => 'default',
                    ];

                    $step = WorkflowStep::create([
                        'workflow_id' => $workflow->id,
                        'step_name' => $stepDef['step_name'],
                        'step_description' => $stepDef['step_name'].' — seeded for testing.',
                        'step_order' => $stepDef['step_order'],
                        'step_group' => $stepDef['step_group'],
                        'action_type' => 'Approve',
                        'assigned_account_id' => $primaryApprover,
                        'max_duration_hours' => $maxDuration,
                        'step_conditions' => $stepConditions,
                    ]);

                    foreach ($stepDef['approvers'] as $approver) {
                        WorkflowStepApprover::create([
                            'step_id' => $step->id,
                            'account_id' => $approver['account_id'],
                            'condition' => $approver['condition'],
                            'order' => $approver['order'],
                        ]);
                    }
                }

                // publishWorkflow: creates WorkflowVersion, advances workflow to Active,
                // and sets the bound form to Active + is_locked = true.
                $versionId = $workflowService->publishWorkflow($workflow->id);

                $workflows[$formCode] = $workflow->fresh();
                $versions[$formCode] = WorkflowVersion::find($versionId);
                $this->command->line("    ✓ {$formCode} published");
            });
        }

        return [$workflows, $versions];
    }

    // ─── Submissions ─────────────────────────────────────────────────────────

    /**
     * @param  array<string, Form>  $forms
     * @param  array<string, WorkflowVersion>  $versions
     * @param  array<int, User>  $studentUsers
     */
    private function seedSubmissions(array $forms, array $versions, array $studentUsers): void
    {
        $targets = [
            // Original six forms
            'MOCK-COE-001' => 80,
            'MOCK-TR-001' => 90,
            'MOCK-LOA-001' => 85,
            'MOCK-FAA-001' => 85,
            'MOCK-EVT-001' => 80,
            'MOCK-LIB-001' => 80,
            // Advanced forms (added in Phase 2)
            'MOCK-RGA-001' => 70,  // 35 branch-A (>50k) + 35 branch-B (<=50k)
            'MOCK-EQR-001' => 70,
            'MOCK-FBR-001' => 70,
            // Phase 3 forms: options_meta qty/text + date range
            'MOCK-ECO-001' => 30,
            'MOCK-SLA-001' => 30,
        ];

        $this->command->info('  → Submissions (per-form idempotent check)...');

        Model::withoutEvents(function () use ($targets, $forms, $versions, $studentUsers) {
            foreach ($targets as $code => $count) {
                $form = $forms[$code] ?? null;
                $version = $versions[$code] ?? null;

                if (! $form || ! $version) {
                    continue;
                }

                $existing = FormSubmission::where('form_id', $form->id)->count();
                if ($existing >= (int) round($count * 0.8)) {
                    $this->command->line("    skip {$code} ({$existing} exist)");

                    continue;
                }

                $this->command->line("    {$form->form_name} ({$count})...");

                if ($code === 'MOCK-RGA-001') {
                    // Branching form: half with amount > 50000 (branch A), half <= 50000 (branch B)
                    $this->createBranchingGrantSubmissions($form, $version, $studentUsers);
                } elseif ($code === 'MOCK-FBR-001') {
                    // Watch-fields form: ~70% without supporting_documents (step 3 Skipped)
                    //                    ~30% with supporting_documents (step 3 active)
                    $this->createWatchFieldsSubmissions($form, $version, $studentUsers, $count);
                } else {
                    $this->createSubmissionsForForm($form, $version, $studentUsers, $count);
                }
            }
        });
    }

    private function createSubmissionsForForm(Form $form, WorkflowVersion $version, array $studentUsers, int $total): void
    {
        $snapshot = $version->steps_snapshot ?? [];
        if (empty($snapshot)) {
            return;
        }

        usort($snapshot, fn ($a, $b) => $a['step_order'] <=> $b['step_order']);
        $groups = array_values(array_unique(array_column($snapshot, 'step_group')));
        sort($groups);

        $payload = $this->buildSamplePayload($form->fields->all());
        $schemaSnap = $form->fields->map(fn ($f) => [
            'id' => $f->id,
            'field_name' => $f->field_name,
            'label' => $f->label,
            'data_type' => $f->data_type,
            'is_required' => $f->is_required,
        ])->values()->all();

        $hasFileField = $form->fields->contains('data_type', 'file');

        $approvedTotal = (int) round($total * 0.25);
        $rejectedTotal = (int) round($total * 0.15);
        $pendingTotal = $total - $approvedTotal - $rejectedTotal;

        $threeMonthsAgo = now()->subMonths(3);
        $submitterId = fn () => $studentUsers[array_rand($studentUsers)]->account_id;

        for ($i = 0; $i < $approvedTotal; $i++) {
            $at = Carbon::instance(fake()->dateTimeBetween($threeMonthsAgo, now()->subWeek()));
            $this->insertSubmission($form, $version, $snapshot, $submitterId(), 'approved', $at, $payload, $schemaSnap, $hasFileField && $i < 5);
        }

        for ($i = 0; $i < $rejectedTotal; $i++) {
            $at = Carbon::instance(fake()->dateTimeBetween($threeMonthsAgo, now()->subDay()));
            $group = $groups[array_rand($groups)];
            $this->insertSubmission($form, $version, $snapshot, $submitterId(), "rejected:{$group}", $at, $payload, $schemaSnap);
        }

        for ($i = 0; $i < $pendingTotal; $i++) {
            $at = Carbon::instance(fake()->dateTimeBetween($threeMonthsAgo, now()));
            $group = $groups[array_rand($groups)];
            $this->insertSubmission($form, $version, $snapshot, $submitterId(), "pending:{$group}", $at, $payload, $schemaSnap);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     */
    private function insertSubmission(
        Form $form,
        WorkflowVersion $version,
        array $snapshot,
        int $accountId,
        string $state,
        Carbon $submittedAt,
        array $payload,
        array $schemaSnap,
        bool $withAttachment = false,
    ): void {
        [$subStatus, $wfStatus, $currentStepId, $currentActorId] =
            $this->resolveSubmissionHeader($state, $snapshot);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $accountId,
            'workflow_version_id' => $version->id,
            'idempotency_key' => (string) Str::uuid(),
            'submission_status' => $subStatus,
            'current_workflow_status' => $wfStatus,
            'current_step_id' => $currentStepId,
            'current_actor_id' => $currentActorId,
            'payload_json' => $payload,
            'schema_snapshot_json' => $schemaSnap,
            'submitted_at' => $submittedAt,
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $this->insertProgressRows($submission, $version, $snapshot, $state, $submittedAt, $payload);

        if ($withAttachment) {
            SubmissionAttachment::create([
                'submission_id' => $submission->id,
                'file_path' => 'attachments/mock/document_'.$submission->id.'.pdf',
                'original_name' => 'supporting_document.pdf',
                'mime_type' => 'application/pdf',
                'uploaded_by' => $accountId,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     * @return array{0: string, 1: string, 2: int|null, 3: int|null}
     */
    private function resolveSubmissionHeader(string $state, array $snapshot): array
    {
        if ($state === 'approved') {
            return ['Approved', 'Approved', null, null];
        }

        if (str_starts_with($state, 'rejected:')) {
            $rejectGroup = (int) substr($state, 9);
            $step = collect($snapshot)->firstWhere('step_group', $rejectGroup);

            return [
                'Rejected',
                'Rejected',
                $step['id'] ?? null,
                $step['assigned_account_id'] ?? ($step['approvers'][0]['account_id'] ?? null),
            ];
        }

        // pending:<group>
        $pendingGroup = (int) substr($state, 8);
        $step = collect($snapshot)->firstWhere('step_group', $pendingGroup);

        return [
            'Pending',
            'Pending',
            $step['id'] ?? ($snapshot[0]['id'] ?? null),
            $step['assigned_account_id'] ?? ($step['approvers'][0]['account_id'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     */
    /**
     * @param  array<string, mixed>  $payload  Used to evaluate branch_condition / watch_fields per step
     */
    private function insertProgressRows(
        FormSubmission $submission,
        WorkflowVersion $version,
        array $snapshot,
        string $state,
        Carbon $submittedAt,
        array $payload = [],
    ): void {
        static $rejectedComments = [
            'Incomplete documentation — please resubmit with complete requirements.',
            'Application does not meet the eligibility criteria.',
            'Missing supporting documents. Please provide the required attachments.',
            'The request does not comply with university policies.',
            'Please verify the information provided and resubmit.',
        ];

        foreach ($snapshot as $step) {
            $group = (int) $step['step_group'];
            $actorId = $step['assigned_account_id'] ?? ($step['approvers'][0]['account_id'] ?? null);

            if (! $actorId) {
                continue;
            }

            // Evaluate branch_condition / watch_fields from the frozen snapshot.
            // When a step should be skipped for this payload, mark it Skipped immediately
            // regardless of the overall submission state — matching runtime engine behaviour.
            if (! empty($payload) && $this->shouldSkipStep($step, $payload)) {
                WorkflowStepProgress::create([
                    'form_id' => $submission->form_id,
                    'submission_id' => $submission->id,
                    'workflow_id' => $version->workflow_id,
                    'workflow_version_id' => $version->id,
                    'step_id' => $step['id'],
                    'actor_id' => $actorId,
                    'action_taken' => null,
                    'comments' => null,
                    'acted_at' => null,
                    'status' => 'Skipped',
                    'started_at' => $submittedAt,
                    'completed_at' => null,
                    'duration_seconds' => null,
                ]);

                continue;
            }

            $resolved = $this->resolveProgressState($state, $group, $submittedAt, $rejectedComments);

            WorkflowStepProgress::create([
                'form_id' => $submission->form_id,
                'submission_id' => $submission->id,
                'workflow_id' => $version->workflow_id,
                'workflow_version_id' => $version->id,
                'step_id' => $step['id'],
                'actor_id' => $actorId,
                'action_taken' => in_array($resolved['status'], ['Approved', 'Rejected'], true) ? $resolved['status'] : null,
                'comments' => $resolved['comments'],
                'acted_at' => $resolved['actedAt'],
                'status' => $resolved['status'],
                'started_at' => $resolved['startedAt'],
                'completed_at' => $resolved['actedAt'],
                'duration_seconds' => ($resolved['startedAt'] && $resolved['actedAt'])
                    ? $resolved['startedAt']->diffInSeconds($resolved['actedAt'])
                    : null,
            ]);
        }
    }

    /**
     * @param  array<int, string>  $rejectedComments
     * @return array{status: string, startedAt: Carbon|null, actedAt: Carbon|null, comments: string|null}
     */
    private function resolveProgressState(string $state, int $group, Carbon $submittedAt, array $rejectedComments): array
    {
        if ($state === 'approved') {
            $startedAt = $submittedAt->clone()->addHours($group * fake()->numberBetween(1, 12));
            $actedAt = $startedAt->clone()->addHours(fake()->numberBetween(1, 36));

            return ['status' => 'Approved', 'startedAt' => $startedAt, 'actedAt' => $actedAt, 'comments' => fake()->optional(0.25)->sentence()];
        }

        if (str_starts_with($state, 'rejected:')) {
            $rejectGroup = (int) substr($state, 9);

            if ($group < $rejectGroup) {
                $startedAt = $submittedAt->clone()->addHours($group * fake()->numberBetween(1, 24));
                $actedAt = $startedAt->clone()->addHours(fake()->numberBetween(2, 24));

                return ['status' => 'Approved', 'startedAt' => $startedAt, 'actedAt' => $actedAt, 'comments' => null];
            }

            if ($group === $rejectGroup) {
                $startedAt = $submittedAt->clone()->addHours(fake()->numberBetween(24, 72));
                $actedAt = $startedAt->clone()->addHours(fake()->numberBetween(2, 48));

                return ['status' => 'Rejected', 'startedAt' => $startedAt, 'actedAt' => $actedAt, 'comments' => $rejectedComments[array_rand($rejectedComments)]];
            }

            // Steps after rejection cascade to Rejected with no actor action
            return ['status' => 'Rejected', 'startedAt' => null, 'actedAt' => null, 'comments' => null];
        }

        // pending:<pendingGroup>
        $pendingGroup = (int) substr($state, 8);

        if ($group < $pendingGroup) {
            $startedAt = $submittedAt->clone()->addHours($group * fake()->numberBetween(1, 24));
            $actedAt = $startedAt->clone()->addHours(fake()->numberBetween(1, 48));

            return ['status' => 'Approved', 'startedAt' => $startedAt, 'actedAt' => $actedAt, 'comments' => fake()->optional(0.2)->sentence()];
        }

        if ($group === $pendingGroup) {
            return [
                'status' => 'Pending',
                'startedAt' => $submittedAt->clone()->addHours(fake()->numberBetween(1, 72)),
                'actedAt' => null,
                'comments' => null,
            ];
        }

        return ['status' => 'Waiting', 'startedAt' => null, 'actedAt' => null, 'comments' => null];
    }

    /** @param  array<int, FormField>  $fields */
    private function buildSamplePayload(array $fields): array
    {
        $payload = [];

        foreach ($fields as $field) {
            $payload[$field->field_name] = match ($field->data_type) {
                'text' => $this->fakeTextValue($field->field_name),
                'email' => fake()->safeEmail(),
                'phone' => '+639'.fake()->numerify('#########'),
                'textarea' => fake()->paragraph(),
                'number' => fake()->numberBetween(1, 100),
                'select', 'radio' => $this->fakeMetaOrPlainOptionValue($field, single: true),
                'checkbox' => $this->fakeMetaOrPlainOptionValue($field, single: false),
                'date' => $this->fakeDateValue($field),
                // 30 % of file fields carry a fake path; the rest are null.
                // This exercises the watch_fields step in MOCK-FBR-001:
                // ~70 % of FBR submissions will have supporting_documents = null → Step 3 Skipped;
                // ~30 % will have a value → Step 3 executes.
                'file' => fake()->boolean(30)
                    ? 'attachments/mock/doc_'.fake()->numerify('####').'.pdf'
                    : null,
                // Non-input layout types: produce no payload value
                'section', 'heading', 'image' => null,
                // Table: array of row objects matching the column definitions
                'table' => $this->fakeTableValue($field),
                default => fake()->words(2, true),
            };
        }

        // Strip null / non-input fields so they don't pollute the payload
        return array_filter($payload, fn ($v) => $v !== null);
    }

    /** @return string|array{start: string, end: string} */
    private function fakeDateValue(FormField $field): string|array
    {
        $start = fake()->dateTimeBetween('now', '+3 months');
        if (($field->date_mode ?? 'single') === 'range') {
            $end = clone $start;
            $end->modify('+'.fake()->numberBetween(1, 5).' days');

            return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
        }

        return $start->format('Y-m-d');
    }

    /** @return array<int, array<string, string|int>> */
    private function fakeTableValue(FormField $field): array
    {
        $columns = ($field->field_options ?? [])['table_columns'] ?? [];
        $rowCount = fake()->numberBetween(1, 3);
        $rows = [];

        for ($r = 0; $r < $rowCount; $r++) {
            $row = [];
            foreach ($columns as $col) {
                $row[$col['id']] = match ($col['type'] ?? 'text') {
                    'number' => fake()->numberBetween(1, 50),
                    'date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
                    'textarea' => fake()->sentence(),
                    default => fake()->words(2, true),
                };
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function fakeTextValue(string $fieldName): string
    {
        return match (true) {
            str_contains($fieldName, 'email') => fake()->safeEmail(),
            str_contains($fieldName, 'phone') => '+639'.fake()->numerify('#########'),
            str_contains($fieldName, '_id') => fake()->numerify('20##-#####'),
            str_contains($fieldName, 'name') => fake()->name(),
            default => fake()->words(3, true),
        };
    }

    private function fakeOptionValue(FormField $field): string
    {
        $options = $field->options ?? [];

        if (is_string($options)) {
            $options = json_decode($options, true) ?? [];
        }

        return ! empty($options) ? $options[array_rand($options)] : 'Option A';
    }

    /**
     * Generate a realistic payload value for checkbox or radio/select fields.
     *
     * When the field has options_meta with requires_qty or requires_text on any option,
     * the frontend encodes the value as a JSON string (via encodeMetaForSubmit in meta.ts).
     * We replicate that exact encoding so that display and parsing code paths are exercised.
     *
     * Checkbox payload (meta):  JSON-encoded MultiMetaSelection array
     *   e.g. '[{"value":"chicken_rice","qty":25},{"value":"beverages","qty":10}]'
     *
     * Radio/select payload (meta):  JSON-encoded SingleMetaSelection object
     *   e.g. '{"value":"other","text":"no pork no lard"}'
     *
     * Plain checkbox (no meta):  comma-separated string  e.g. "val1,val2"
     * Plain radio/select (no meta):  plain string         e.g. "val1"
     */
    private function fakeMetaOrPlainOptionValue(FormField $field, bool $single): string
    {
        $meta = $field->options_meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $anyQty = ! empty($meta) && collect($meta)->contains(fn ($o) => ! empty($o['requires_qty']));
        $anyText = ! empty($meta) && collect($meta)->contains(fn ($o) => ! empty($o['requires_text']));

        if (! empty($meta) && ($anyQty || $anyText)) {
            if ($single) {
                // Pick one random option and fill in qty / text where required.
                $opt = $meta[array_rand($meta)];
                $sel = ['value' => $opt['value'] ?? $opt['label']];
                if (! empty($opt['requires_qty'])) {
                    $min = (int) ($opt['min_qty'] ?? 0);
                    $max = isset($opt['max_qty']) && $opt['max_qty'] !== null ? (int) $opt['max_qty'] : $min + 20;
                    $step = max(1, (int) ($opt['step'] ?? 1));
                    $range = (int) floor(($max - $min) / $step);
                    $sel['qty'] = $min + fake()->numberBetween(0, max(0, $range)) * $step;
                }

                if (! empty($opt['requires_text'])) {
                    $sel['text'] = fake()->words(3, true);
                }

                return json_encode($sel, JSON_UNESCAPED_UNICODE);
            }

            // Checkbox: pick 1–3 random options (without duplicates) and fill qty / text.
            $picked = (array) array_rand($meta, min(fake()->numberBetween(1, 3), count($meta)));
            $selections = [];
            foreach ($picked as $idx) {
                $opt = $meta[$idx];
                $sel = ['value' => $opt['value'] ?? $opt['label']];
                if (! empty($opt['requires_qty'])) {
                    $min = (int) ($opt['min_qty'] ?? 0);
                    $max = isset($opt['max_qty']) && $opt['max_qty'] !== null ? (int) $opt['max_qty'] : $min + 20;
                    $step = max(1, (int) ($opt['step'] ?? 1));
                    $range = (int) floor(($max - $min) / $step);
                    $sel['qty'] = $min + fake()->numberBetween(0, max(0, $range)) * $step;
                }

                if (! empty($opt['requires_text'])) {
                    $sel['text'] = fake()->words(3, true);
                }

                $selections[] = $sel;
            }

            return json_encode($selections, JSON_UNESCAPED_UNICODE);
        }

        // Plain options (no meta qty/text) — fall back to simple string encoding.
        $options = $field->options ?? [];
        if (is_string($options)) {
            $options = json_decode($options, true) ?? [];
        }

        if (empty($options)) {
            return $single ? 'Option A' : 'Option A';
        }

        if ($single) {
            return (string) $options[array_rand($options)];
        }

        // Checkbox without meta: comma-separated (frontend plain encoding)
        $count = fake()->numberBetween(1, min(3, count($options)));
        $keys = (array) array_rand($options, $count);

        return implode(',', array_map(fn ($k) => (string) $options[$k], $keys));
    }

    /**
     * Determine whether a workflow step should be skipped for a given submission payload.
     *
     * Mirrors WorkflowProgressService::shouldSkipStep() so that seeded progress rows
     * reflect what the runtime engine would produce.
     *
     * Logic (in order):
     *  1. If watch_fields is non-empty → skip when ALL listed fields are absent (null / '').
     *  2. Else if branch_condition is set → skip when the condition evaluates to false.
     *  3. Otherwise → do not skip.
     *
     * @param  array<string, mixed>  $step  A step entry from the workflow version snapshot.
     * @param  array<string, mixed>  $payload  The submission payload.
     */
    private function shouldSkipStep(array $step, array $payload): bool
    {
        $conditions = $step['step_conditions'] ?? [];
        $watchFields = $conditions['watch_fields'] ?? [];

        if (! empty($watchFields)) {
            // Skip if ALL watch fields are absent from the payload
            $allAbsent = true;
            foreach ($watchFields as $fieldName) {
                $value = $payload[$fieldName] ?? null;
                if ($value !== null && $value !== '') {
                    $allAbsent = false;
                    break;
                }
            }

            return $allAbsent;
        }

        $branchCondition = $conditions['branch_condition'] ?? null;
        if ($branchCondition !== null) {
            // Skip if the branch condition evaluates to FALSE
            return ! WorkflowConditionEvaluator::evaluate($branchCondition, $payload);
        }

        return false;
    }

    /**
     * Create 35 branch-A (amount_requested > 50 000) and 35 branch-B (<=50 000)
     * submissions for MOCK-RGA-001 so that both conditional workflow paths are exercised.
     *
     * @param  array<int, User>  $studentUsers
     */
    private function createBranchingGrantSubmissions(
        Form $form,
        WorkflowVersion $version,
        array $studentUsers,
    ): void {
        $snapshot = $version->steps_snapshot ?? [];
        if (empty($snapshot)) {
            return;
        }

        usort($snapshot, fn ($a, $b) => $a['step_order'] <=> $b['step_order']);
        $groups = array_values(array_unique(array_column($snapshot, 'step_group')));
        sort($groups);

        $schemaSnap = $form->fields->map(fn ($f) => [
            'id' => $f->id,
            'field_name' => $f->field_name,
            'label' => $f->label,
            'data_type' => $f->data_type,
            'is_required' => $f->is_required,
        ])->values()->all();

        $threeMonthsAgo = now()->subMonths(3);
        $submitterId = fn () => $studentUsers[array_rand($studentUsers)]->account_id;

        // Distribute 35 submissions per branch across the usual approved/rejected/pending split
        foreach (['high' => 35, 'low' => 35] as $branch => $count) {
            $approvedCount = (int) round($count * 0.25);
            $rejectedCount = (int) round($count * 0.15);
            $pendingCount = $count - $approvedCount - $rejectedCount;

            for ($i = 0; $i < $approvedCount; $i++) {
                $at = Carbon::instance(fake()->dateTimeBetween($threeMonthsAgo, now()->subWeek()));
                $payload = $this->buildGrantPayload($form->fields->all(), $branch);
                $this->insertSubmission($form, $version, $snapshot, $submitterId(), 'approved', $at, $payload, $schemaSnap);
            }

            for ($i = 0; $i < $rejectedCount; $i++) {
                $at = Carbon::instance(fake()->dateTimeBetween($threeMonthsAgo, now()->subDay()));
                $group = $groups[array_rand($groups)];
                $payload = $this->buildGrantPayload($form->fields->all(), $branch);
                $this->insertSubmission($form, $version, $snapshot, $submitterId(), "rejected:{$group}", $at, $payload, $schemaSnap);
            }

            for ($i = 0; $i < $pendingCount; $i++) {
                $at = Carbon::instance(fake()->dateTimeBetween($threeMonthsAgo, now()));
                $group = $groups[array_rand($groups)];
                $payload = $this->buildGrantPayload($form->fields->all(), $branch);
                $this->insertSubmission($form, $version, $snapshot, $submitterId(), "pending:{$group}", $at, $payload, $schemaSnap);
            }
        }
    }

    /**
     * Build a sample payload for the Research Grant Application form,
     * overriding amount_requested for the given branch.
     *
     * @param  array<int, FormField>  $fields
     * @param  'high'|'low'  $branch
     * @return array<string, mixed>
     */
    private function buildGrantPayload(array $fields, string $branch): array
    {
        $payload = $this->buildSamplePayload($fields);

        $payload['amount_requested'] = $branch === 'high'
            ? fake()->numberBetween(50001, 200000)
            : fake()->numberBetween(5000, 50000);

        return $payload;
    }

    /**
     * Create submissions for forms that use watch_fields on a step.
     *
     * For MOCK-FBR-001, Step 3 (Admin Document Verification) is Skipped when
     * supporting_documents is absent. This method creates:
     *   ~70 % of submissions WITHOUT supporting_documents → Step 3 Skipped
     *   ~30 % of submissions WITH supporting_documents    → Step 3 active
     *
     * @param  array<int, User>  $studentUsers
     */
    private function createWatchFieldsSubmissions(
        Form $form,
        WorkflowVersion $version,
        array $studentUsers,
        int $total,
    ): void {
        $snapshot = $version->steps_snapshot ?? [];
        if (empty($snapshot)) {
            return;
        }

        usort($snapshot, fn ($a, $b) => $a['step_order'] <=> $b['step_order']);
        $groups = array_values(array_unique(array_column($snapshot, 'step_group')));
        sort($groups);

        $schemaSnap = $form->fields->map(fn ($f) => [
            'id' => $f->id,
            'field_name' => $f->field_name,
            'label' => $f->label,
            'data_type' => $f->data_type,
            'is_required' => $f->is_required,
        ])->values()->all();

        $threeMonthsAgo = now()->subMonths(3);
        $submitterId = fn () => $studentUsers[array_rand($studentUsers)]->account_id;

        // 70 % without the watch field → Step 3 Skipped; 30 % with it → Step 3 active
        $withoutDoc = (int) round($total * 0.7);
        $withDoc = $total - $withoutDoc;

        foreach ([false => $withoutDoc, true => $withDoc] as $hasDoc => $count) {
            $approvedCount = (int) round($count * 0.25);
            $rejectedCount = (int) round($count * 0.15);
            $pendingCount = $count - $approvedCount - $rejectedCount;

            $buildPayload = function () use ($form, $hasDoc): array {
                $payload = $this->buildSamplePayload($form->fields->all());
                if ($hasDoc) {
                    $payload['supporting_documents'] = 'attachments/mock/doc_'.fake()->numerify('####').'.pdf';
                } else {
                    unset($payload['supporting_documents']);
                }

                return $payload;
            };

            for ($i = 0; $i < $approvedCount; $i++) {
                $at = Carbon::instance(fake()->dateTimeBetween($threeMonthsAgo, now()->subWeek()));
                $this->insertSubmission($form, $version, $snapshot, $submitterId(), 'approved', $at, $buildPayload(), $schemaSnap);
            }

            for ($i = 0; $i < $rejectedCount; $i++) {
                $at = Carbon::instance(fake()->dateTimeBetween($threeMonthsAgo, now()->subDay()));
                $group = $groups[array_rand($groups)];
                $this->insertSubmission($form, $version, $snapshot, $submitterId(), "rejected:{$group}", $at, $buildPayload(), $schemaSnap);
            }

            for ($i = 0; $i < $pendingCount; $i++) {
                $at = Carbon::instance(fake()->dateTimeBetween($threeMonthsAgo, now()));
                $group = $groups[array_rand($groups)];
                $this->insertSubmission($form, $version, $snapshot, $submitterId(), "pending:{$group}", $at, $buildPayload(), $schemaSnap);
            }
        }
    }

    // ─── Snapshots ───────────────────────────────────────────────────────────

    /**
     * Seed verification snapshots for a sample of approved and rejected progress rows.
     * Calls SnapshotService::createFromProgress() so the payload and HMAC match production.
     *
     * @param  array<string, Form>  $forms
     */
    private function seedSnapshots(array $forms): void
    {
        $this->command->info('  → Snapshots...');

        if (\App\Modules\VerificationSnapshot\Models\Snapshot::count() >= 20) {
            $this->command->info('  → Snapshots already seeded. Skipping.');

            return;
        }

        $formIds = collect($forms)
            ->filter()
            ->map(fn (Form $f) => $f->id)
            ->values()
            ->all();

        if (empty($formIds)) {
            return;
        }

        $snapshotService = app(SnapshotService::class);

        // Up to 3 Approved + 1 Rejected progress rows per form
        $approvedProgress = WorkflowStepProgress::query()
            ->whereIn('form_id', $formIds)
            ->where('status', 'Approved')
            ->whereNotNull('acted_at')
            ->orderBy('id')
            ->get()
            ->groupBy('form_id')
            ->flatMap(fn ($group) => $group->take(3));

        $rejectedProgress = WorkflowStepProgress::query()
            ->whereIn('form_id', $formIds)
            ->where('status', 'Rejected')
            ->whereNotNull('acted_at')
            ->orderBy('id')
            ->get()
            ->groupBy('form_id')
            ->flatMap(fn ($group) => $group->take(1));

        $count = 0;
        $skipped = 0;

        foreach ($approvedProgress->merge($rejectedProgress) as $progress) {
            try {
                $snapshotService->createFromProgress($progress->id);
                $count++;
            } catch (\Throwable $e) {
                $skipped++;
                $this->command->warn("    ⚠ Skipped progress #{$progress->id}: {$e->getMessage()}");
            }
        }

        $this->command->line("    ✓ {$count} snapshots created".($skipped ? ", {$skipped} skipped" : ''));
    }

    // ─── Error Reports ───────────────────────────────────────────────────────

    /** @param  array<int, User>  $users */
    private function seedSlots(array $forms): void
    {
        $form = $forms['MOCK-FBR-001'] ?? null;

        if (! $form) {
            return;
        }

        if (Slot::where('form_id', $form->id)->count() >= 10) {
            $this->command->info('  → Slots already seeded. Skipping.');

            return;
        }

        $this->command->info('  → Seeding sample facility slots (MOCK-FBR-001)...');

        $facilityIds = Facility::where('is_active', true)->pluck('id')->all();
        if (empty($facilityIds)) {
            return;
        }

        $submissions = FormSubmission::where('form_id', $form->id)->limit(20)->pluck('account_id', 'id');

        foreach ($submissions as $submissionId => $accountId) {
            $facilityId = $facilityIds[array_rand($facilityIds)];
            $date = fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d');

            Slot::create([
                'form_id' => $form->id,
                'submission_id' => $submissionId,
                'account_id' => $accountId,
                'facility_id' => $facilityId,
                'date' => $date,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'status' => fake()->randomElement(['Pending', 'Approved', 'Rejected']),
            ]);
        }
    }

    private function seedErrorReports(array $users): void
    {
        if (ErrorReport::count() >= 10) {
            $this->command->info('  → Error reports already seeded. Skipping.');

            return;
        }

        $this->command->info('  → Error reports (15)...');

        $statuses = ['new', 'reviewed', 'in_progress', 'resolved', 'dismissed'];

        $messages = [
            "TypeError: Cannot read properties of null (reading 'id')",
            'Uncaught ReferenceError: useForm is not defined',
            'Network Error: Failed to fetch /api/submissions',
            'ChunkLoadError: Loading chunk 3 failed.',
            'Error: Form submission failed with status 422',
            'UnhandledPromiseRejection: Inertia page component not found',
            'Warning: Each child in a list should have a unique "key" prop',
            'Error: The payload field is missing or malformed',
            'TypeError: map is not a function on workflow_steps',
            'SyntaxError: Unexpected token in JSON at position 0',
            'Error: CSRF token mismatch. Please refresh and try again.',
            'Error: 500 Internal Server Error on form load',
            "TypeError: Cannot destructure property 'data' of undefined",
            'Error: Submission attachment upload timed out',
            'Error: Workflow version not found for this submission',
        ];

        $urlPaths = ['/dashboard', '/forms', '/submissions', '/workflows', '/staff/approvals', '/admin/users', '/reports'];

        for ($i = 0; $i < 15; $i++) {
            $user = $users[array_rand($users)];
            $createdAt = fake()->dateTimeBetween(now()->subWeeks(3), now());

            ErrorReport::create([
                'message' => $messages[$i],
                'stack' => 'Error at '.fake()->word().'.tsx:'.fake()->numberBetween(10, 200)
                    ."\n  at ".fake()->word()."()\n  at React.render()",
                'url' => 'https://auflow.auf.edu.ph'.$urlPaths[$i % count($urlPaths)],
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0',
                'comment' => $i % 3 === 0 ? fake()->sentence() : null,
                'user_id' => $user->account_id,
                'status' => $statuses[$i % count($statuses)],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
