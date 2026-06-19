import { usePage } from "@inertiajs/react";
import type { SharedData } from "@/types";

export function DashboardHero() {
    const { auth } = usePage<SharedData>().props;
    const firstName = auth?.user?.name?.split(" ")[0] ?? "there";
    const today = new Intl.DateTimeFormat(undefined, {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric",
    }).format(new Date());

    return (
        <div className="flex items-center justify-between gap-6 pb-6">
            <div>
                <h1 className="text-xl font-semibold text-foreground">
                    Welcome back, {firstName}
                </h1>
                <p className="text-sm text-muted-foreground mt-1">
                    Here&rsquo;s what your workflows look like today.
                </p>
            </div>
            <p className="text-xs text-muted-foreground/60 hidden sm:block shrink-0">{today}</p>
        </div>
    );
}
