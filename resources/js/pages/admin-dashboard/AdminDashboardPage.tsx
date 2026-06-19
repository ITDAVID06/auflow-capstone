import React, { lazy, Suspense } from "react";
import { usePage, Head } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import type { SharedData } from "@/types";
import {
    FileText,
    Clock,
    CheckCircle,
    TrendingUp,
    Users,
    GitBranch,
    Building2,
    LayoutGrid,
} from "lucide-react";
import { BentoGrid, BentoCard } from "@/components/BentoGrid";
import { StatCard } from "./components/StatCard";
import { PendingApprovalsCard } from "./components/PendingApprovalsCard";
import { RecentSubmissionsCard } from "./components/RecentSubmissionsCard";
import { RecentActivityCard } from "./components/RecentActivityCard";
import { SubmissionTrendsChart } from "./components/SubmissionTrendsChart";
import { PopularFormsChart } from "./components/PopularFormsChart";
import { TopApproversCard } from "./components/TopApproversCard";
import { DashboardHero } from "./components/DashboardHero";

const ReportWidgetRow = lazy(() => import("./components/ReportWidgetRow"));

export interface KpiMetrics {
    totalSubmissions: number;
    totalSubmissionsChange: number;
    totalSubmissionsSparkline: number[];
    pendingReview: number;
    pendingReviewChange: number;
    pendingReviewSparkline: number[];
    completedToday: number;
    completedTodayChange: number;
    completedTodaySparkline: number[];
    approvalRate: number;
    approvalRateChange: number;
    approvalRateSparkline: number[];
}

export interface Submission {
    id: number;
    form_id?: number;
    submission_id?: number;
    form_name?: string;
    type?: string;
    requester?: string;
    requester_name?: string;
    submitter?: string;
    status: string;
    submittedDate?: string;
    submitted_at?: string;
}

export interface PendingItem {
    id: number;
    form_id: number;
    submission_id: number;
    form_name: string;
    requester: string;
    status: string;
    submittedDate: string;
}

export interface Activity {
    id: number;
    category: string;
    action: string;
    status: string;
    statusColor: string;
    summary: string;
    description: string;
    date: string;
    ip: string;
}

export interface TrendPoint {
    date: string;
    submissions: number;
}

export interface FormPopularity {
    form_name: string;
    submission_count: number;
}

export interface Approver {
    approver_name: string;
    total_approvals: number;
    avg_time_hours: number;
    performance: "fast" | "average" | "slow";
}

export interface Metrics {
    totalSubmissions: number;
    totalFacilities: number;
    pendingApprovalsOrgWide: number;
    pendingApprovalsSuperAdmin: number;
    forms: { active: number; inactive: number };
    users: { active: number; inactive: number };
    workflows: { active: number; inactive: number };
}

interface PageProps extends SharedData {
    kpiMetrics: KpiMetrics;
    submissionTrends: TrendPoint[];
    popularForms: FormPopularity[];
    topApprovers: Approver[];
    pendingApprovals: PendingItem[];
    recentSubmissions: Submission[];
    recentActivity: Activity[];
    metrics: Metrics;
}

// ─── Platform Overview card ───────────────────────────────────────────────────

function MetricPill({
    label,
    value,
    icon,
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
}) {
    return (
        <div className="flex items-center gap-2.5 rounded-lg bg-foreground/[0.03] border border-border/20 px-3 py-2.5">
            <span className="text-foreground/40 shrink-0" aria-hidden="true">{icon}</span>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground truncate">{label}</p>
                <p className="text-sm font-semibold text-foreground tabular-nums">{value}</p>
            </div>
        </div>
    );
}

function PlatformOverviewCard({ metrics }: { metrics: Metrics }) {
    return (
        <div className="rounded-xl border border-border/30 bg-card/50 p-5 shadow-sm hover:shadow-md hover:border-border/50 transition-[box-shadow,border-color] duration-200 h-full flex flex-col">
            <h2 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-4">
                <LayoutGrid className="w-4 h-4 text-foreground/50" />
                Platform Overview
            </h2>
            <div className="grid grid-cols-2 gap-2 flex-1">
                <MetricPill
                    label="Active Forms"
                    value={metrics.forms.active}
                    icon={<FileText className="w-3.5 h-3.5" />}
                />
                <MetricPill
                    label="Inactive Forms"
                    value={metrics.forms.inactive}
                    icon={<FileText className="w-3.5 h-3.5" />}
                />
                <MetricPill
                    label="Active Users"
                    value={metrics.users.active}
                    icon={<Users className="w-3.5 h-3.5" />}
                />
                <MetricPill
                    label="Inactive Users"
                    value={metrics.users.inactive}
                    icon={<Users className="w-3.5 h-3.5" />}
                />
                <MetricPill
                    label="Active Workflows"
                    value={metrics.workflows.active}
                    icon={<GitBranch className="w-3.5 h-3.5" />}
                />
                <MetricPill
                    label="Draft Workflows"
                    value={metrics.workflows.inactive}
                    icon={<GitBranch className="w-3.5 h-3.5" />}
                />
                <div className="col-span-2">
                    <MetricPill
                        label="Facilities"
                        value={metrics.totalFacilities}
                        icon={<Building2 className="w-3.5 h-3.5" />}
                    />
                </div>
            </div>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function AdminDashboardPage() {
    const {
        kpiMetrics,
        submissionTrends,
        popularForms,
        topApprovers,
        pendingApprovals,
        recentSubmissions,
        recentActivity,
        metrics,
    } = usePage<PageProps>().props;

    return (
        <AppLayout>
            <Head title="Admin Dashboard" />

            <div className="space-y-4 p-6">
                {/* ── Header ── */}
                <DashboardHero />

                {/* ── Bento grid ──────────────────────────────────────────
                    xl (4-col) layout:
                    Row 1 : [KPI ×4]
                    Row 2–3: [Trends (2-col, 2-row)] | [Top Approvers (1-col)]     | [Pending (1-col)]
                             [Trends cont.]           | [Popular Forms (1-col)]    | [Activity (1-col)]
                    Row 4 : [Recent Submissions (2-col)] | [Platform Overview (2-col)]
                    Row 5 : [Report Widgets (4-col)]
                ─────────────────────────────────────────────────────────── */}
                <BentoGrid>
                    {/* ── Row 1 — KPI Stat Cards ── */}
                    <BentoCard delay={0}>
                        <StatCard
                            title="Total Submissions"
                            value={kpiMetrics.totalSubmissions}
                            change={kpiMetrics.totalSubmissionsChange}
                            sparkline={kpiMetrics.totalSubmissionsSparkline}
                            icon={<FileText className="w-3.5 h-3.5" />}
                        />
                    </BentoCard>
                    <BentoCard delay={0.05}>
                        <StatCard
                            title="Pending Review"
                            value={kpiMetrics.pendingReview}
                            change={kpiMetrics.pendingReviewChange}
                            sparkline={kpiMetrics.pendingReviewSparkline}
                            icon={<Clock className="w-3.5 h-3.5" />}
                        />
                    </BentoCard>
                    <BentoCard delay={0.1}>
                        <StatCard
                            title="Completed Today"
                            value={kpiMetrics.completedToday}
                            change={kpiMetrics.completedTodayChange}
                            sparkline={kpiMetrics.completedTodaySparkline}
                            icon={<CheckCircle className="w-3.5 h-3.5" />}
                        />
                    </BentoCard>
                    <BentoCard delay={0.15}>
                        <StatCard
                            title="Approval Rate"
                            value={`${kpiMetrics.approvalRate.toFixed(1)}%`}
                            change={kpiMetrics.approvalRateChange}
                            sparkline={kpiMetrics.approvalRateSparkline}
                            icon={<TrendingUp className="w-3.5 h-3.5" />}
                        />
                    </BentoCard>

                    {/* ── Rows 2–3 — Main analysis section ── */}
                    {/* Submission Trends: 2-col wide, 2 rows tall on xl */}
                    <BentoCard colSpan={2} rowSpan={2} delay={0.2}>
                        <SubmissionTrendsChart data={submissionTrends} />
                    </BentoCard>

                    {/* Right column row-2 */}
                    <BentoCard delay={0.25}>
                        <TopApproversCard approvers={topApprovers} />
                    </BentoCard>
                    <BentoCard delay={0.3}>
                        <PendingApprovalsCard items={pendingApprovals} />
                    </BentoCard>

                    {/* Right column row-3 (fills the row-span-2 gap beside Trends) */}
                    <BentoCard delay={0.35}>
                        <PopularFormsChart data={popularForms} />
                    </BentoCard>
                    <BentoCard delay={0.4}>
                        <RecentActivityCard activities={recentActivity} />
                    </BentoCard>

                    {/* ── Row 4 — Tables + Platform Overview ── */}
                    <BentoCard colSpan={2} delay={0.45}>
                        <RecentSubmissionsCard submissions={recentSubmissions} />
                    </BentoCard>
                    <BentoCard colSpan={2} delay={0.5}>
                        <PlatformOverviewCard metrics={metrics} />
                    </BentoCard>

                    {/* ── Row 5 — Report widgets (lazy-loaded) ── */}
                    <BentoCard colSpan={4} delay={0.55} className="p-0 border-0 bg-transparent shadow-none hover:shadow-none hover:border-0">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Suspense
                                fallback={
                                    <div className="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        {[0, 1].map((i) => (
                                            <div key={i} className="rounded-xl border border-border/30 bg-card/50 p-5 h-[280px] animate-pulse">
                                                <div className="h-4 w-44 rounded bg-foreground/10 mb-4" />
                                                <div className="h-[210px] rounded bg-foreground/5" />
                                            </div>
                                        ))}
                                    </div>
                                }
                            >
                                <ReportWidgetRow />
                            </Suspense>
                        </div>
                    </BentoCard>
                </BentoGrid>
            </div>
        </AppLayout>
    );
}
