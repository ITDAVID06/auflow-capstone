<?php

namespace Tests\Feature;

use App\Mail\SubmissionCompletedMail;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Actions\SendSubmissionCompletionEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendSubmissionCompletionEmailActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_run_sends_completion_email_synchronously(): void
    {
        Mail::fake();

        $creator = $this->createUser('completion_creator', 'completion_creator@test.com');

        $form = Form::create([
            'form_name' => 'Completion Action Form',
            'form_code' => 'COMPLETE_ACTION_'.uniqid(),
            'description' => 'Completion action send test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        SendSubmissionCompletionEmail::run(
            form: $form,
            submissionId: 9901,
            outcome: 'approved',
            submitterEmail: 'submitter@test.com',
            submitterName: 'Test Submitter',
            viewUrl: route('student-dashboard.submission.view', ['formId' => $form->id, 'submissionId' => 9901])
        );

        Mail::assertSent(SubmissionCompletedMail::class, function (SubmissionCompletedMail $mail): bool {
            return $mail->hasTo('submitter@test.com')
                && $mail->submissionId === 9901
                && $mail->outcome === 'approved'
                && $mail->submitterName === 'Test Submitter';
        });

        Mail::assertNotQueued(SubmissionCompletedMail::class);
    }

    private function createUser(string $username, string $email): User
    {
        return User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
    }
}
