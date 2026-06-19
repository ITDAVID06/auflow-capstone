import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Trophy, Clock } from "lucide-react";
import type { Approver } from "../AdminDashboardPage";
import { statusBadgeClass } from "./status";

interface Props {
    approvers: Approver[];
}

function initials(name: string): string {
    return name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
}

export function TopApproversCard({ approvers }: Props) {
    return (
        <div className="rounded-xl border border-border/30 bg-card/50 p-5 shadow-sm hover:shadow-md hover:border-border/50 transition-[box-shadow,border-color] duration-200 flex flex-col h-full">
            <h2 className="text-sm font-semibold text-foreground flex items-center gap-2 mb-5">
                <Trophy className="w-4 h-4 text-foreground/60" aria-hidden="true" />
                Top Approvers
            </h2>

            <div className="flex-1">
                {approvers.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-full min-h-32 text-center">
                        <div className="w-12 h-12 rounded-lg bg-foreground/5 flex items-center justify-center mb-4">
                            <Trophy className="w-6 h-6 text-muted-foreground/50" aria-hidden="true" />
                        </div>
                        <p className="text-sm text-muted-foreground mb-1">No approver data</p>
                        <p className="text-xs text-muted-foreground/60">Stats will appear here</p>
                    </div>
                ) : (
                    <div className="space-y-1">
                        {approvers.map((approver) => (
                            <div key={approver.approver_name} className="flex items-center gap-4 py-3 px-2 rounded-md hover:bg-foreground/[0.02] transition-colors">
                                <Avatar className="w-8 h-8 shrink-0">
                                    <AvatarFallback className="bg-foreground/5 text-foreground/70 text-xs font-medium">
                                        {initials(approver.approver_name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-foreground truncate">{approver.approver_name}</p>
                                    <div className="flex items-center gap-2 mt-1">
                                        <span className="text-xs text-muted-foreground">{approver.total_approvals} approvals</span>
                                        <span className="text-xs text-muted-foreground/40">•</span>
                                        <span className="flex items-center gap-1 text-xs text-muted-foreground/60">
                                            <Clock className="w-3 h-3" aria-hidden="true" />
                                            {approver.avg_time_hours.toFixed(1)}h avg
                                        </span>
                                    </div>
                                </div>
                                <span className={`text-[11px] font-medium px-2 py-0.5 rounded capitalize ${statusBadgeClass(approver.performance)}`}>
                                    {approver.performance}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
