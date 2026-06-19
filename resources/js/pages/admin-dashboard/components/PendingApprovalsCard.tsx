import { Link } from "@inertiajs/react";
import { Clock, ChevronRight, ArrowRight } from "lucide-react";
import type { PendingItem } from "../AdminDashboardPage";
import { statusBadgeClass } from "./status";
import { formatDate } from "@/utils/dateTime";

interface Props {
    items: PendingItem[];
}

export function PendingApprovalsCard({ items }: Props) {
    return (
        <div className="rounded-xl border border-border/30 bg-card/50 p-5 shadow-sm hover:shadow-md hover:border-border/50 transition-[box-shadow,border-color] duration-200 flex flex-col h-full">
            <div className="flex items-center justify-between mb-5">
                <h2 className="text-sm font-semibold text-foreground">Pending Approvals</h2>
                <Link
                    href={route("admin-submissions.my-pending")}
                    className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
                >
                    View All
                    <ArrowRight className="w-3 h-3" aria-hidden="true" />
                </Link>
            </div>

            <div className="flex-1">
                {items.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-full min-h-32 text-center">
                        <div className="w-12 h-12 rounded-lg bg-foreground/5 flex items-center justify-center mb-4">
                            <Clock className="w-6 h-6 text-muted-foreground/50" aria-hidden="true" />
                        </div>
                        <p className="text-sm text-muted-foreground mb-1">No pending approvals</p>
                        <p className="text-xs text-muted-foreground/60">All caught up</p>
                    </div>
                ) : (
                    <div className="space-y-1">
                        {items.map((item) => (
                            <Link
                                key={item.id}
                                href={route("admin-submissions.show", { formId: item.form_id, submissionId: item.submission_id })}
                                className="flex items-start gap-4 py-3 px-2 rounded-md hover:bg-foreground/[0.02] transition-colors group"
                            >
                                <div className="w-8 h-8 rounded-md bg-foreground/5 flex items-center justify-center shrink-0 mt-0.5">
                                    <Clock className="w-4 h-4 text-foreground/60" aria-hidden="true" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-foreground truncate">{item.form_name}</p>
                                    <p className="text-xs text-muted-foreground mt-1">by {item.requester}</p>
                                    <div className="flex items-center gap-2 mt-2">
                                        <span className={`text-[11px] font-medium px-2 py-0.5 rounded ${statusBadgeClass(item.status)}`}>
                                            {item.status}
                                        </span>
                                        <span className="text-xs text-muted-foreground/60">{formatDate(item.submittedDate, { month: "short", day: "numeric", year: "numeric" })}</span>
                                    </div>
                                </div>
                                        <ChevronRight className="w-4 h-4 text-muted-foreground/40 group-hover:text-muted-foreground transition-colors shrink-0 mt-2" aria-hidden="true" />
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
