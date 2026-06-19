/** Maps a status or performance string to semantic badge classes. */
export function statusBadgeClass(status: string): string {
    const k = (status ?? "").toLowerCase();

    if (k === "approved" || k === "fast") {
        return "bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400";
    }
    if (k === "rejected" || k === "slow") {
        return "bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400";
    }
    if (k === "pending" || k === "average") {
        return "bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400";
    }
    return "bg-foreground/10 text-foreground/70";
}
