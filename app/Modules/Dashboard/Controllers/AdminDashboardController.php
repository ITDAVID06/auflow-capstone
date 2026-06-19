<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminSubmissions\Services\AdminSubmissionsService;
use App\Modules\FormBuilder\Models\Facility;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AdminDashboardController extends Controller
{
    public function __construct(
        protected AdminSubmissionsService $service
    ) {}

    public function index(): Response
    {
        $currentAccountId = (int) (Auth::user()->account_id ?? Auth::id());

        // =========================
        // METRICS
        // =========================
        $metrics = [
            'totalSubmissions' => WorkflowStepProgress::count(),
            'totalFacilities' => Facility::count(),
            'pendingApprovalsOrgWide' => WorkflowStepProgress::whereIn('status', ['Pending', 'In Review'])->count(),
            'pendingApprovalsSuperAdmin' => WorkflowStepProgress::whereIn('status', ['Pending', 'In Review'])
                ->whereHas('step', function ($q) use ($currentAccountId) {
                    $q->where('assigned_account_id', $currentAccountId);
                })
                ->count(),
            'forms' => [
                'active' => Form::where('status', 'active')->count(),
                'inactive' => Form::where('status', 'inactive')->count(),
            ],
            'users' => [
                'active' => User::where('user_status_id', 1)->count(),
                'inactive' => User::where('user_status_id', 2)->count(),
            ],
            'workflows' => [
                'active' => Workflow::where('status', 'active')->count(),
                'inactive' => Workflow::where('status', 'draft')->count(),
            ],
        ];

        // =========================
        // RECENT SUBMISSIONS
        // =========================
        $recentRequests = $this->service->getSystemSubmissions(null, null, 5);

        // =========================
        // PENDING APPROVALS
        // =========================
        $pendingApprovals = WorkflowStepProgress::with('form', 'step')
            ->with(['canonicalSubmission.submitter.profile'])
            ->whereIn('status', ['Pending', 'In Review'])
            ->whereHas('step', function ($q) use ($currentAccountId) {
                $q->where('assigned_account_id', $currentAccountId);
            })
            ->latest()
            ->take(5)
            ->get()
            ->map(function (WorkflowStepProgress $r) {
                $requesterName = $this->resolveRequesterName($r);

                return [
                    'id' => $r->id,
                    'form_id' => $r->form_id,
                    'submission_id' => $r->submission_id,
                    'form_name' => $r->form->form_name ?? '—',
                    'requester' => $requesterName,
                    'status' => $r->status,
                    'submittedDate' => optional($r->created_at)->toDateString(),
                ];
            });

        // =========================
        // RECENT ACTIVITY
        // =========================
        $recentActivity = $this->getRecentActivity(3);

        // =========================
        // NEW SHARDS-STYLE DASHBOARD DATA
        // =========================

        // ── Real daily counts for the last 14 days (used for sparklines + change calc) ──
        $dailyCounts = collect();
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyCounts->push(WorkflowStepProgress::whereDate('created_at', $date)->count());
        }

        // last 7 days vs previous 7 days → % change
        $last7 = $dailyCounts->slice(7)->sum();
        $prev7 = $dailyCounts->slice(0, 7)->sum();
        $totalSubmissionsChange = $prev7 > 0
            ? round((($last7 - $prev7) / $prev7) * 100, 1)
            : ($last7 > 0 ? 100.0 : 0.0);

        $last7Sparkline = $dailyCounts->slice(7)->values()->toArray();

        // ── Completed today vs yesterday ──
        $completedToday = WorkflowStepProgress::where('status', 'Approved')
            ->whereDate('completed_at', now())
            ->count();
        $completedYesterday = WorkflowStepProgress::where('status', 'Approved')
            ->whereDate('completed_at', now()->subDay())
            ->count();
        $completedTodayChange = $completedYesterday > 0
            ? round((($completedToday - $completedYesterday) / $completedYesterday) * 100, 1)
            : ($completedToday > 0 ? 100.0 : 0.0);

        // Approved per day over last 7 days for sparkline
        $completedSparkline = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $completedSparkline->push(
                WorkflowStepProgress::where('status', 'Approved')
                    ->whereDate('completed_at', $date)
                    ->count()
            );
        }

        // ── Approval rate ──
        $approvedCount = WorkflowStepProgress::where('status', 'Approved')->count();
        $totalCount = WorkflowStepProgress::count();
        $approvalRate = $totalCount > 0 ? ($approvedCount / $totalCount) * 100 : 0;

        // Approval rate last week (submissions created in prev 7 days)
        $prevApproved = WorkflowStepProgress::where('status', 'Approved')
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();
        $prevTotal = WorkflowStepProgress::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
        $prevApprovalRate = $prevTotal > 0 ? ($prevApproved / $prevTotal) * 100 : 0;
        $approvalRateChange = $prevApprovalRate > 0
            ? round($approvalRate - $prevApprovalRate, 1)
            : 0.0;

        // Approval rate per day over last 7 days for sparkline
        $approvalRateSparkline = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayTotal = WorkflowStepProgress::whereDate('created_at', $date)->count();
            $dayApproved = WorkflowStepProgress::where('status', 'Approved')
                ->whereDate('created_at', $date)
                ->count();
            $approvalRateSparkline->push(
                $dayTotal > 0 ? round(($dayApproved / $dayTotal) * 100) : 0
            );
        }

        // ── Pending review sparkline (new submissions per day over 7d as proxy) ──
        $pendingSparkline = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $pendingSparkline->push(
                WorkflowStepProgress::whereIn('status', ['Pending', 'In Review'])
                    ->whereDate('created_at', $date)
                    ->count()
            );
        }
        $pendingLast7 = $pendingSparkline->sum();
        $pendingPrev7 = collect();
        for ($i = 13; $i >= 7; $i--) {
            $date = now()->subDays($i);
            $pendingPrev7->push(
                WorkflowStepProgress::whereIn('status', ['Pending', 'In Review'])
                    ->whereDate('created_at', $date)
                    ->count()
            );
        }
        $pendingPrev7Sum = $pendingPrev7->sum();
        $pendingReviewChange = $pendingPrev7Sum > 0
            ? round((($pendingLast7 - $pendingPrev7Sum) / $pendingPrev7Sum) * 100, 1)
            : ($pendingLast7 > 0 ? 100.0 : 0.0);

        // KPI Metrics with sparklines — all real data, no mocks
        $kpiMetrics = [
            'totalSubmissions' => $metrics['totalSubmissions'],
            'totalSubmissionsChange' => $totalSubmissionsChange,
            'totalSubmissionsSparkline' => $last7Sparkline,
            'pendingReview' => $metrics['pendingApprovalsOrgWide'],
            'pendingReviewChange' => $pendingReviewChange,
            'pendingReviewSparkline' => $pendingSparkline->values()->toArray(),
            'completedToday' => $completedToday,
            'completedTodayChange' => $completedTodayChange,
            'completedTodaySparkline' => $completedSparkline->values()->toArray(),
            'approvalRate' => round($approvalRate, 1),
            'approvalRateChange' => $approvalRateChange,
            'approvalRateSparkline' => $approvalRateSparkline->values()->toArray(),
        ];

        // Submission trends for chart (last 30 days) — real counts, 0 when none
        $submissionTrends = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = WorkflowStepProgress::whereDate('created_at', $date)->count();
            $submissionTrends->push([
                'date' => $date->format('M d'),
                'submissions' => $count,
            ]);
        }

        // =========================
        // POPULAR FORMS (Most Submitted)
        // =========================
        $popularForms = WorkflowStepProgress::query()->from('tbl_workflow_step_progress as wsp')
            ->join('tbl_form as f', 'f.id', '=', 'wsp.form_id')
            ->select('f.form_name', DB::raw('COUNT(*) as submission_count'))
            ->groupBy('f.id', 'f.form_name')
            ->orderByDesc('submission_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'form_name' => strlen($item->form_name) > 30
                        ? substr($item->form_name, 0, 30).'...'
                        : $item->form_name,
                    'submission_count' => (int) $item->submission_count,
                ];
            })
            ->toArray();

        // =========================
        // TOP APPROVERS (Performance & Speed)
        // =========================
        $topApprovers = WorkflowStepProgress::query()->from('tbl_workflow_step_progress as wsp')
            ->join('tbl_workflow_step as ws', 'ws.id', '=', 'wsp.step_id')
            ->join('tbl_user as u', 'u.account_id', '=', 'ws.assigned_account_id')
            ->leftJoin('tbl_userprofile as up', 'up.account_id', '=', 'u.account_id')
            ->whereIn('wsp.status', ['Approved', 'Rejected'])
            ->whereNotNull('wsp.duration_seconds')
            ->select(
                DB::raw('COALESCE(
                    NULLIF(CONCAT(TRIM(up.first_name), " ", TRIM(up.last_name)), " "),
                    u.username,
                    u.email
                ) as approver_name'),
                DB::raw('COUNT(*) as total_approvals'),
                DB::raw('ROUND(AVG(wsp.duration_seconds) / 3600, 2) as avg_time_hours')
            )
            ->groupBy('ws.assigned_account_id', 'u.username', 'u.email', 'up.first_name', 'up.last_name')
            ->orderByDesc('total_approvals')
            ->limit(5)
            ->get()
            ->map(function ($approver) {
                // Determine performance based on average time
                $performance = 'average';
                if ($approver->avg_time_hours < 24) {
                    $performance = 'fast';
                } elseif ($approver->avg_time_hours > 72) {
                    $performance = 'slow';
                }

                return [
                    'approver_name' => $approver->approver_name,
                    'total_approvals' => (int) $approver->total_approvals,
                    'avg_time_hours' => (float) $approver->avg_time_hours,
                    'performance' => $performance,
                ];
            })
            ->toArray();

        // =========================
        // RETURN
        // =========================
        return Inertia::render('admin-dashboard/AdminDashboardPage', [
            'kpiMetrics' => $kpiMetrics,
            'submissionTrends' => $submissionTrends->toArray(),
            'popularForms' => $popularForms,
            'topApprovers' => $topApprovers,
            'pendingApprovals' => $pendingApprovals,
            'recentSubmissions' => $recentRequests,
            'recentActivity' => $recentActivity,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get recent activity logs
     */
    private function getRecentActivity(int $limit = 6): array
    {
        return DB::table('tbl_audit_log')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                $date = \Carbon\Carbon::parse($log->created_at)->format('M d, Y h:i A');

                $statusColor = match (strtolower($log->status ?? '')) {
                    'success', 'verified', 'approved' => 'green',
                    'warning' => 'yellow',
                    'failed', 'rejected', 'mismatch' => 'red',
                    default => 'gray',
                };

                $summary = $log->actor_name
                    ? "{$log->actor_name} (".($log->actor_role ?? 'User').')'
                    : 'System';

                if ($log->action) {
                    $summary .= ' — '.ucwords(str_replace('_', ' ', $log->action));
                }

                return [
                    'id' => $log->id,
                    'category' => ucfirst(str_replace('_', ' ', (string) $log->category)),
                    'action' => ucwords(str_replace('_', ' ', (string) $log->action)),
                    'status' => $log->status,
                    'statusColor' => $statusColor,
                    'summary' => $summary,
                    'description' => $log->description ?? '',
                    'date' => $date,
                    'ip' => $log->ip_address,
                ];
            })
            ->toArray();
    }

    /**
     * Resolve the display name for a pending approval requester from canonical submissions.
     */
    private function resolveRequesterName(WorkflowStepProgress $progress): string
    {
        try {
            $submission = $this->resolveCanonicalSubmission($progress);

            if (! $submission || ! $submission->account_id) {
                return 'Unknown';
            }

            $submitter = $submission->submitter;
            $fullName = trim((string) ($submitter?->profile?->first_name ?? '').' '.(string) ($submitter?->profile?->last_name ?? ''));

            if ($fullName !== '') {
                return $fullName;
            }

            return $submitter?->username ?: ('Account '.$submission->account_id);
        } catch (\Throwable) {
            return 'Unknown';
        }
    }

    private function resolveCanonicalSubmission(WorkflowStepProgress $progress): ?FormSubmission
    {
        if ($progress->relationLoaded('canonicalSubmission') && $progress->canonicalSubmission) {
            return $progress->canonicalSubmission;
        }

        return FormSubmission::query()
            ->with('submitter.profile')
            ->find($progress->submission_id);
    }
}
