<?php

namespace App\Modules\ErrorReports\Controllers;

use App\Actions\ErrorReports\StoreErrorReportAction;
use App\Actions\ErrorReports\UpdateErrorReportStatusAction;
use App\Http\Controllers\Controller;
use App\Modules\ErrorReports\Models\ErrorReport;
use App\Modules\ErrorReports\Requests\StoreErrorReportRequest;
use App\Modules\ErrorReports\Requests\UpdateErrorReportRequest;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ErrorReportController extends Controller
{
    public function store(StoreErrorReportRequest $request, StoreErrorReportAction $action, NotificationService $notifications): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['user_id'] = auth()->user()?->account_id;
            $report = $action->execute($data);
            $this->notifyAdmins($report, $notifications);
        } catch (\Throwable $e) {
            Log::error('Failed to store error report', ['exception' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'Could not save report.'], 500);
        }

        return response()->json(['ok' => true], 201);
    }

    private function notifyAdmins(ErrorReport $report, NotificationService $notifications): void
    {
        try {
            $recipientIds = DB::table('tbl_user_role as ur')
                ->join('tbl_role_permission as rp', 'ur.role_id', '=', 'rp.role_id')
                ->join('tbl_permission as p', 'rp.permission_id', '=', 'p.id')
                ->where('p.slug', 'error-reports.manage')
                ->pluck('ur.account_id')
                ->unique()
                ->values()
                ->all();

            if (empty($recipientIds)) {
                return;
            }

            $notifications->send(
                $recipientIds,
                'error_report_submitted',
                [
                    'title' => 'New Bug Report',
                    'message' => 'A new bug report has been submitted and requires triage.',
                    'action_url' => route('admin.error-reports.index'),
                    'action_text' => 'View Reports',
                    'related_type' => 'error_report',
                    'related_id' => $report->id,
                    'icon' => 'bug',
                    'priority' => 'normal',
                    'triggered_by' => $report->user_id,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send error report notifications', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $allowedStatuses = ['new', 'reviewed', 'in_progress', 'dismissed', 'resolved'];

        $query = ErrorReport::with('reporter:account_id,username,email')
            ->orderByDesc('created_at');

        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }

        $reports = $query->paginate(15);

        return Inertia::render('ErrorReports/Index', [
            'reports' => $reports->getCollection()->map(fn (ErrorReport $r) => [
                'id' => $r->id,
                'message' => $r->message,
                'stack' => $r->stack,
                'url' => $r->url,
                'user_agent' => $r->user_agent,
                'comment' => $r->comment,
                'user_id' => $r->user_id,
                'reporter_name' => $r->reporter
                    ? ($r->reporter->username ?? $r->reporter->email)
                    : null,
                'status' => $r->status,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ]),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
            'filters' => [
                'status' => in_array($status, $allowedStatuses, true) ? $status : null,
            ],
        ]);
    }

    public function update(UpdateErrorReportRequest $request, int $report, UpdateErrorReportStatusAction $action): RedirectResponse
    {
        $errorReport = ErrorReport::findOrFail($report);
        $action->execute($errorReport, $request->validated('status'));

        return back();
    }
}
