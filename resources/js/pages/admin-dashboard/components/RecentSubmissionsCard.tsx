import { Link } from "@inertiajs/react";
import { FileText, ArrowRight } from "lucide-react";
import type { Submission } from "../AdminDashboardPage";
import { statusBadgeClass } from "./status";
import { formatDate } from "@/utils/dateTime";

interface Props {
    submissions: Submission[];
}

export function RecentSubmissionsCard({ submissions }: Props) {
    return (
        <div className="rounded-xl border border-border/30 bg-card/50 p-5 shadow-sm hover:shadow-md hover:border-border/50 transition-[box-shadow,border-color] duration-200 h-full">
            <div className="flex items-center justify-between mb-5">
                <h2 className="text-sm font-semibold text-foreground">Recent Submissions</h2>
                <Link
                    href={route("admin-submissions.index")}
                    className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
                >
                    View All
                    <ArrowRight className="w-3 h-3" aria-hidden="true" />
                </Link>
            </div>

            {submissions.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-16 text-center">
                    <div className="w-12 h-12 rounded-lg bg-foreground/5 flex items-center justify-center mb-4">
                        <FileText className="w-6 h-6 text-muted-foreground/50" aria-hidden="true" />
                    </div>
                    <p className="text-sm text-muted-foreground mb-1">No submissions yet</p>
                    <p className="text-xs text-muted-foreground/60">New submissions will appear here</p>
                </div>
            ) : (
                <div className="space-y-1">
                    {submissions.map((s) => {
                        const requester = s.requester ?? s.requester_name ?? s.submitter ?? "—";
                        const date = s.submittedDate ?? s.submitted_at;
                        const formName = s.form_name ?? s.type ?? "Untitled";
                        const href =
                            s.form_id != null && s.submission_id != null
                                ? route("admin-submissions.show", { formId: s.form_id, submissionId: s.submission_id })
                                : null;
                        const Row = href ? Link : "div";
                        return (
                            <Row
                                key={s.id}
                                {...(href ? { href } : {})}
                                className="flex items-start gap-4 py-3 px-2 rounded-md hover:bg-foreground/[0.02] transition-colors"
                            >
                                <div className="w-8 h-8 rounded-md bg-foreground/5 flex items-center justify-center shrink-0 mt-0.5">
                                    <FileText className="w-4 h-4 text-foreground/60" aria-hidden="true" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-foreground truncate">{formName}</p>
                                    <p className="text-xs text-muted-foreground mt-1">by {requester}</p>
                                    <div className="flex items-center gap-2 mt-2">
                                        <span className={`text-[11px] font-medium px-2 py-0.5 rounded ${statusBadgeClass(s.status)}`}>
                                            {s.status}
                                        </span>
                                        {date && (
                                            <span className="text-xs text-muted-foreground/60">
                                                {formatDate(date, { month: "short", day: "numeric", year: "numeric" })}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </Row>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
