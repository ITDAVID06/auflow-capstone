import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Ban, Loader2, Wrench } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

// ── Types ─────────────────────────────────────────────────────────────────────

type ReportStatus = 'new' | 'reviewed' | 'in_progress' | 'dismissed' | 'resolved';

interface ErrorReport {
    id: number;
    message: string;
    stack: string;
    url: string;
    user_agent: string;
    comment: string | null;
    user_id: number | null;
    reporter_name: string | null;
    status: ReportStatus;
    created_at: string;
    updated_at: string;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

type FilterStatus = 'all' | ReportStatus;

interface Props {
    reports: ErrorReport[];
    pagination: PaginationMeta;
    filters: { status: string | null };
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const STATUS_LABELS: Record<ReportStatus, string> = {
    new: 'New',
    reviewed: 'Reviewed',
    in_progress: 'Ongoing',
    dismissed: 'Dismissed',
    resolved: 'Resolved',
};

const STATUS_ICONS: Partial<Record<ReportStatus, React.ElementType>> = {
    in_progress: Wrench,
    dismissed: Ban,
};

function StatusLabel({ status }: { status: ReportStatus }) {
    const Icon = STATUS_ICONS[status];
    return (
        <span className="flex items-center gap-1">
            {Icon && <Icon className="h-3 w-3" />}
            {STATUS_LABELS[status]}
        </span>
    );
}

const STATUS_VARIANTS: Record<ReportStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    new: 'default',
    reviewed: 'secondary',
    in_progress: 'secondary',
    dismissed: 'outline',
    resolved: 'outline',
};

const ALL_STATUSES: ReportStatus[] = ['new', 'reviewed', 'in_progress', 'dismissed', 'resolved'];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// ── Component ─────────────────────────────────────────────────────────────────

export default function Index({ reports, pagination, filters }: Props) {
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const [processing, setProcessing] = useState<number | null>(null);
    const [statusFilter, setStatusFilter] = useState<FilterStatus>(
        (filters.status as FilterStatus) ?? 'all',
    );

    const toggleRow = (id: number) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const updateStatus = (id: number, status: ReportStatus) => {
        setProcessing(id);
        router.patch(
            route('admin.error-reports.update', id),
            { status },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(null),
                onError: () => {
                    toast.error('Failed to update status. Please try again.');
                },
            },
        );
    };

    const goToPage = (page: number) => {
        router.get(
            route('admin.error-reports.index'),
            { page, status: statusFilter === 'all' ? undefined : statusFilter },
            { preserveScroll: true },
        );
    };

    const applyStatusFilter = (value: FilterStatus) => {
        setStatusFilter(value);
        router.get(
            route('admin.error-reports.index'),
            { status: value === 'all' ? undefined : value, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title="Bug Reports" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Bug Reports</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {pagination.total} report{pagination.total !== 1 ? 's' : ''} total
                        </p>
                    </div>
                    <Select value={statusFilter} onValueChange={(v) => applyStatusFilter(v as FilterStatus)}>
                        <SelectTrigger className="h-9 w-[140px] rounded-md border-border/80 bg-background text-sm shadow-sm" aria-label="Filter by status">
                            <SelectValue placeholder="All" />
                        </SelectTrigger>
                        <SelectContent align="end" className="w-[140px]">
                            <SelectItem value="all">All</SelectItem>
                            <SelectItem value="new">New</SelectItem>
                            <SelectItem value="reviewed">Reviewed</SelectItem>
                            <SelectItem value="in_progress">
                                <StatusLabel status="in_progress" />
                            </SelectItem>
                            <SelectItem value="dismissed">
                                <StatusLabel status="dismissed" />
                            </SelectItem>
                            <SelectItem value="resolved">Resolved</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-lg border border-border/60">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-border/60 bg-muted/40">
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">#</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Message</th>
                                <th className="hidden px-4 py-3 text-left font-medium text-muted-foreground md:table-cell">User</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Status</th>
                                <th className="hidden px-4 py-3 text-left font-medium text-muted-foreground lg:table-cell">Date</th>
                                <th className="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {reports.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No error reports yet.
                                    </td>
                                </tr>
                            )}
                            {reports.map((report) => (
                                <React.Fragment key={report.id}>
                                    <tr
                                        className="cursor-pointer border-b border-border/40 transition-colors hover:bg-muted/30 last:border-0"
                                        onClick={() => toggleRow(report.id)}
                                    >
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {report.id}
                                        </td>
                                        <td className="max-w-xs px-4 py-3">
                                            <span
                                                className="line-clamp-2 font-mono text-xs"
                                                title={report.message}
                                            >
                                                {report.message}
                                            </span>
                                        </td>
                                        <td className="hidden px-4 py-3 text-muted-foreground md:table-cell">
                                            {report.reporter_name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant={STATUS_VARIANTS[report.status]}>
                                                <StatusLabel status={report.status} />
                                            </Badge>
                                        </td>
                                        <td className="hidden px-4 py-3 text-xs text-muted-foreground lg:table-cell">
                                            {formatDate(report.created_at)}
                                        </td>
                                        <td
                                            className="px-4 py-3 text-right"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <div className="flex items-center justify-end gap-2">
                                                {processing === report.id && (
                                                    <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                                                )}
                                                <Select
                                                    value={report.status}
                                                    onValueChange={(value) =>
                                                        updateStatus(report.id, value as ReportStatus)
                                                    }
                                                    disabled={processing === report.id}
                                                >
                                                    <SelectTrigger className="h-8 w-36 text-xs">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {ALL_STATUSES.map((s) => (
                                                            <SelectItem key={s} value={s} className="text-xs">
                                                                <StatusLabel status={s} />
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </td>
                                    </tr>

                                    {expanded.has(report.id) && (
                                        <tr className="border-b border-border/40 bg-muted/20 last:border-0">
                                            <td colSpan={6} className="px-4 py-4">
                                                <div className="space-y-4 text-sm">
                                                    {report.comment && (
                                                        <div>
                                                            <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                                User Comment
                                                            </p>
                                                            <p>{report.comment}</p>
                                                        </div>
                                                    )}
                                                    <div>
                                                        <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                            URL
                                                        </p>
                                                        <p className="break-all font-mono text-xs">{report.url}</p>
                                                    </div>
                                                    <div>
                                                        <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                            User Agent
                                                        </p>
                                                        <p className="break-all font-mono text-xs">{report.user_agent}</p>
                                                    </div>
                                                    <div>
                                                        <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                            Stack Trace
                                                        </p>
                                                        <pre className="max-h-64 overflow-auto rounded bg-muted px-3 py-2 text-[11px] leading-relaxed whitespace-pre-wrap break-all">
                                                            {report.stack}
                                                        </pre>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </React.Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {pagination.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Page {pagination.current_page} of {pagination.last_page}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={pagination.current_page === 1}
                                onClick={() => goToPage(pagination.current_page - 1)}
                            >
                                Previous
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={pagination.current_page === pagination.last_page}
                                onClick={() => goToPage(pagination.current_page + 1)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
