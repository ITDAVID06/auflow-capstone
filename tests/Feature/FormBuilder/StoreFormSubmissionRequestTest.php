<?php

namespace Tests\Feature\FormBuilder;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Requests\StoreFormSubmissionRequest;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Tests\TestCase;

class StoreFormSubmissionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_rules_include_max_length_for_text_fields(): void
    {
        $user = User::create([
            'username' => 'submitter_'.uniqid(),
            'email' => 'sub_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Req Test Form',
            'form_code' => 'RTF-'.uniqid(),
            'status' => 'Active',
            'version' => 1,
            'is_locked' => true,
            'created_by' => $user->account_id,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'notes',
            'label' => 'Notes',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 0,
        ]);

        Workflow::create([
            'form_id' => $form->id,
            'workflow_name' => 'Test Workflow',
            'workflow_type' => 'standard',
            'status' => 'Active',
            'created_by' => $user->account_id,
        ]);

        $this->actingAs($user);

        $httpRequest = Request::create("/user/forms/{$form->id}/submit", 'POST', ['notes' => 'hello']);
        $httpRequest->setRouteResolver(fn () => tap(new \Illuminate\Routing\Route('POST', '/user/forms/{id}/submit', []), function ($r) use ($form) {
            $r->bind(request());
            $r->setParameter('id', $form->id);
        }));

        $formRequest = StoreFormSubmissionRequest::createFrom($httpRequest);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app(\Illuminate\Routing\Redirector::class));

        $rules = $formRequest->rules();

        $this->assertArrayHasKey('notes', $rules);
        $this->assertStringContainsString('max:10000', $rules['notes']);
    }

    public function test_rules_include_max_length_for_textarea_fields(): void
    {
        $user = User::create([
            'username' => 'submitter_'.uniqid(),
            'email' => 'sub_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Req Test Form',
            'form_code' => 'RTF-'.uniqid(),
            'status' => 'Active',
            'version' => 1,
            'is_locked' => true,
            'created_by' => $user->account_id,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'body',
            'label' => 'Body',
            'data_type' => 'textarea',
            'is_required' => false,
            'field_order' => 0,
        ]);

        Workflow::create([
            'form_id' => $form->id,
            'workflow_name' => 'Test Workflow',
            'workflow_type' => 'standard',
            'status' => 'Active',
            'created_by' => $user->account_id,
        ]);

        $this->actingAs($user);

        $httpRequest = Request::create("/user/forms/{$form->id}/submit", 'POST', ['body' => 'hello']);
        $httpRequest->setRouteResolver(fn () => tap(new \Illuminate\Routing\Route('POST', '/user/forms/{id}/submit', []), function ($r) use ($form) {
            $r->bind(request());
            $r->setParameter('id', $form->id);
        }));

        $formRequest = StoreFormSubmissionRequest::createFrom($httpRequest);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app(\Illuminate\Routing\Redirector::class));

        $rules = $formRequest->rules();

        $this->assertArrayHasKey('body', $rules);
        $this->assertStringContainsString('max:100000', $rules['body']);
    }

    public function test_authorize_returns_true(): void
    {
        $formRequest = new StoreFormSubmissionRequest;
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app(\Illuminate\Routing\Redirector::class));

        $this->assertTrue($formRequest->authorize());
    }

    public function test_dynamic_rules_enforce_slot_range_types_and_non_input_guard(): void
    {
        $user = User::create([
            'username' => 'submitter_'.uniqid(),
            'email' => 'sub_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Req Dynamic Rule Form',
            'form_code' => 'RDRF-'.uniqid(),
            'status' => 'Active',
            'version' => 1,
            'is_locked' => true,
            'created_by' => $user->account_id,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'mode',
            'label' => 'Mode',
            'data_type' => 'text',
            'is_required' => true,
            'field_order' => 0,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'intro_section',
            'label' => 'Intro Section',
            'data_type' => 'section',
            'is_required' => true,
            'field_order' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'slot_pick',
            'label' => 'Slot Pick',
            'data_type' => 'date',
            'is_required' => true,
            'use_slots' => true,
            'date_mode' => 'single',
            'conditions' => [[
                'field_name' => 'mode',
                'operator' => 'not_equals',
                'value' => 'slot',
                'action' => 'hide',
            ]],
            'field_order' => 2,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'range_pick',
            'label' => 'Range Pick',
            'data_type' => 'date',
            'is_required' => true,
            'date_mode' => 'range',
            'conditions' => [[
                'field_name' => 'mode',
                'operator' => 'not_equals',
                'value' => 'range',
                'action' => 'hide',
            ]],
            'field_order' => 3,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'contact_email',
            'label' => 'Contact Email',
            'data_type' => 'email',
            'is_required' => true,
            'field_order' => 4,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'amount',
            'label' => 'Amount',
            'data_type' => 'number',
            'is_required' => true,
            'field_options' => [
                'min' => 10,
                'max' => 20,
            ],
            'field_order' => 5,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'schedule_table',
            'label' => 'Schedule Table',
            'data_type' => 'table',
            'is_required' => true,
            'field_options' => [
                'table_columns' => [
                    ['id' => 'col_a', 'label' => 'Column A', 'type' => 'text'],
                    ['id' => 'col_b', 'label' => 'Column B', 'type' => 'number'],
                ],
            ],
            'field_order' => 6,
        ]);

        Workflow::create([
            'form_id' => $form->id,
            'workflow_name' => 'Test Workflow',
            'workflow_type' => 'standard',
            'status' => 'Active',
            'created_by' => $user->account_id,
        ]);

        $slotId = DB::table('tbl_slots')->insertGetId([
            'form_id' => $form->id,
            'submission_id' => 1,
            'account_id' => $user->account_id,
            'facility_id' => null,
            'date' => '2026-04-28',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $basePayload = [
            'contact_email' => 'valid@example.com',
            'amount' => 12,
            'schedule_table' => json_encode([
                ['col_a' => 'Value A', 'col_b' => 2],
            ]),
        ];

        $hiddenRules = $this->buildSubmissionRules($form, array_merge($basePayload, ['mode' => 'hidden']));
        $this->assertArrayNotHasKey('intro_section', $hiddenRules);

        $hiddenValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, ['mode' => 'hidden']));
        $this->assertTrue($hiddenValidator->passes(), json_encode($hiddenValidator->errors()->toArray()));

        $emailValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'hidden',
            'contact_email' => 'not-an-email',
        ]));
        $this->assertTrue($emailValidator->fails());
        $this->assertArrayHasKey('contact_email', $emailValidator->errors()->toArray());

        $numberTypeValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'hidden',
            'amount' => 'abc',
        ]));
        $this->assertTrue($numberTypeValidator->fails());
        $this->assertArrayHasKey('amount', $numberTypeValidator->errors()->toArray());

        $numberMinValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'hidden',
            'amount' => 5,
        ]));
        $this->assertTrue($numberMinValidator->fails());
        $this->assertArrayHasKey('amount', $numberMinValidator->errors()->toArray());

        $tableJsonValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'hidden',
            'schedule_table' => 'plain-string',
        ]));
        $this->assertTrue($tableJsonValidator->fails());
        $this->assertArrayHasKey('schedule_table', $tableJsonValidator->errors()->toArray());

        $tableSchemaValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'hidden',
            'schedule_table' => json_encode([
                ['unexpected' => 'x'],
            ]),
        ]));
        $this->assertTrue($tableSchemaValidator->fails());
        $this->assertArrayHasKey('schedule_table', $tableSchemaValidator->errors()->toArray());

        $slotRequiredValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'slot',
        ]));
        $this->assertTrue($slotRequiredValidator->fails());
        $this->assertArrayHasKey('slots', $slotRequiredValidator->errors()->toArray());

        $slotStructureValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'slot',
            'slots' => [[
                'slot_id' => $slotId + 99999,
                'date' => '04-28-2026',
                'start_time' => '9am',
                'end_time' => '10am',
            ]],
        ]));
        $this->assertTrue($slotStructureValidator->fails());
        $this->assertArrayHasKey('slots.0.slot_id', $slotStructureValidator->errors()->toArray());
        $this->assertArrayHasKey('slots.0.date', $slotStructureValidator->errors()->toArray());
        $this->assertArrayHasKey('slots.0.start_time', $slotStructureValidator->errors()->toArray());
        $this->assertArrayHasKey('slots.0.end_time', $slotStructureValidator->errors()->toArray());

        $slotMissingIdValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'slot',
            'slots' => [[
                'date' => '2026-04-28',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]],
        ]));
        $this->assertTrue($slotMissingIdValidator->fails());
        $this->assertArrayHasKey('slots.0.slot_id', $slotMissingIdValidator->errors()->toArray());

        $rangeRequiredValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'range',
        ]));
        $this->assertTrue($rangeRequiredValidator->fails());
        $this->assertArrayHasKey('date_ranges', $rangeRequiredValidator->errors()->toArray());

        $rangeMissingEndValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'range',
            'date_ranges' => [[
                'start' => '2026-05-01',
            ]],
        ]));
        $this->assertTrue($rangeMissingEndValidator->fails());
        $this->assertArrayHasKey('date_ranges.0.end', $rangeMissingEndValidator->errors()->toArray());

        $rangeDateFormatValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'range',
            'date_ranges' => [[
                'start' => '05-01-2026',
                'end' => '2026-05-03',
            ]],
        ]));
        $this->assertTrue($rangeDateFormatValidator->fails());
        $this->assertArrayHasKey('date_ranges.0.start', $rangeDateFormatValidator->errors()->toArray());

        $rangeOrderValidator = $this->makeSubmissionValidator($form, array_merge($basePayload, [
            'mode' => 'range',
            'date_ranges' => [[
                'start' => '2026-05-03',
                'end' => '2026-05-01',
            ]],
        ]));
        $this->assertTrue($rangeOrderValidator->fails());
        $this->assertArrayHasKey('date_ranges.0.end', $rangeOrderValidator->errors()->toArray());
    }

    private function buildSubmissionRules(Form $form, array $payload): array
    {
        $httpRequest = Request::create("/user/forms/{$form->id}/submit", 'POST', $payload);
        $httpRequest->setRouteResolver(function () use ($form, $httpRequest) {
            $route = new Route('POST', '/user/forms/{id}/submit', []);
            $route->bind($httpRequest);
            $route->setParameter('id', $form->id);

            return $route;
        });

        $formRequest = StoreFormSubmissionRequest::createFrom($httpRequest);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app(\Illuminate\Routing\Redirector::class));

        return $formRequest->rules();
    }

    private function makeSubmissionValidator(Form $form, array $payload): \Illuminate\Validation\Validator
    {
        $rules = $this->buildSubmissionRules($form, $payload);

        return ValidatorFacade::make($payload, $rules);
    }

    public function test_file_field_rule_accepts_webp_extension(): void
    {
        Storage::fake('local');

        $user = User::create([
            'username' => 'submitter_'.uniqid(),
            'email' => 'sub_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'WebP Form',
            'form_code' => 'WPF-'.uniqid(),
            'status' => 'Active',
            'version' => 1,
            'is_locked' => true,
            'created_by' => $user->account_id,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'photo',
            'label' => 'Photo',
            'data_type' => 'file',
            'is_required' => true,
            'field_order' => 0,
        ]);

        $this->actingAs($user);

        $fakeWebp = UploadedFile::fake()->create('screenshot.webp', 200, 'image/webp');

        $httpRequest = Request::create("/user/forms/{$form->id}/submit", 'POST');
        $httpRequest->files->set('photo', $fakeWebp);
        $httpRequest->setRouteResolver(function () use ($form, $httpRequest) {
            $route = new Route('POST', '/user/forms/{id}/submit', []);
            $route->bind($httpRequest);
            $route->setParameter('id', $form->id);

            return $route;
        });

        $formRequest = StoreFormSubmissionRequest::createFrom($httpRequest);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app(\Illuminate\Routing\Redirector::class));

        $rules = $formRequest->rules();

        $this->assertArrayHasKey('photo', $rules);
        $this->assertStringContainsString('webp', $rules['photo']);

        // Validation with a real webp file must pass (not rejected on mimes).
        $validator = ValidatorFacade::make(['photo' => $fakeWebp], ['photo' => $rules['photo']]);
        $this->assertFalse($validator->fails(), 'webp file should pass file field validation: '.$validator->errors()->first());
    }
}
