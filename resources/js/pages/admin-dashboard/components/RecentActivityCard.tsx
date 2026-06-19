import { UserPlus, FileEdit, CheckCircle, XCircle, Edit, Shield } from "lucide-react";
import type { Activity } from "../AdminDashboardPage";
import { statusBadgeClass } from "./status";
import { formatDate } from "@/utils/dateTime";

interface Props {
    activities: Activity[];
}

function actionIcon(action: string) {
    const a = action.toLowerCase();
    if (a.includes("create")) return <UserPlus className="w-4 h-4" />;
    if (a.includes("update") || a.includes("edit")) return <Edit className="w-4 h-4" />;
    if (a.includes("approve")) return <CheckCircle className="w-4 h-4" />;
    if (a.includes("reject")) return <XCircle className="w-4 h-4" />;
    if (a.includes("permission")) return <Shield className="w-4 h-4" />;
    return <FileEdit className="w-4 h-4" />;
}

export function RecentActivityCard({ activities }: Props) {
    return (
        <div className="rounded-xl border border-border/30 bg-card/50 p-5 shadow-sm hover:shadow-md hover:border-border/50 transition-[box-shadow,border-color] duration-200 flex flex-col h-full">
            <h2 className="text-sm font-semibold text-foreground mb-5">Recent Activity</h2>

            <div className="flex-1">
                {activities.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-full min-h-32 text-center">
                        <div className="w-12 h-12 rounded-lg bg-foreground/5 flex items-center justify-center mb-4">
                            <FileEdit className="w-6 h-6 text-muted-foreground/50" aria-hidden="true" />
                        </div>
                        <p className="text-sm text-muted-foreground mb-1">No recent activity</p>
                        <p className="text-xs text-muted-foreground/60">Activity will appear here</p>
                    </div>
                ) : (
                    <div className="space-y-1">
                        {activities.map((activity) => (
                            <div key={activity.id} className="flex items-start gap-4 py-3 px-2 rounded-md hover:bg-foreground/[0.02] transition-colors">
                                <div className="w-8 h-8 rounded-md bg-foreground/5 flex items-center justify-center shrink-0 mt-0.5 text-foreground/60" aria-hidden="true">
                                    {actionIcon(activity.action)}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-foreground leading-tight truncate">{activity.action}</p>
                                    <p className="text-xs text-muted-foreground mt-1 line-clamp-1">{activity.summary}</p>
                                    <div className="flex items-center gap-2 mt-2">
                                        <span className={`text-[11px] font-medium px-2 py-0.5 rounded ${statusBadgeClass(activity.status)}`}>
                                            {activity.status}
                                        </span>
                                        <span className="text-xs text-muted-foreground/60">{formatDate(activity.date, { month: "short", day: "numeric", year: "numeric" })}</span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
