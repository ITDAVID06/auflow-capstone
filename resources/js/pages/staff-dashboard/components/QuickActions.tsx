import React from "react";
import { Link, router, usePage } from "@inertiajs/react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Plus, ClipboardList, Search } from "lucide-react";

type QuickActionsProps = {
  className?: string;
};

export default function QuickActions({ className = "" }: QuickActionsProps) {
  const { url } = usePage();
  const params = new URLSearchParams(url.split("?")[1] || "");
  const [q, setQ] = React.useState(params.get("q") ?? "");

  const apply = () => {
    router.get(
      route("staff-dashboard.index"),
      { q: q || undefined },
      { preserveState: true, preserveScroll: true }
    );
  };

  return (
    <div className={`flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 ${className}`}>
      {/* Search */}
      <div className="relative flex-1 sm:max-w-sm" data-tour="staff-search">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
        <Input
          type="text"
          name="q"
          autoComplete="off"
          placeholder="Search requests..."
          className="pl-9 h-9 text-sm border-border/80 bg-background"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && apply()}
        />
      </div>

      {/* Spacer */}
      <div className="hidden sm:flex-1 sm:block" />

      {/* Action buttons */}
      <div className="flex flex-wrap items-center gap-2 sm:justify-end">
        <Button
          asChild
          variant="outline"
          size="sm"
          data-tour="staff-view-all"
          className="h-9 border-border/80 text-sm gap-1.5 flex-1 sm:flex-none"
        >
          <Link href={route("staff-dashboard.requests")}>
            <ClipboardList className="h-4 w-4" />
            View All
          </Link>
        </Button>

        <Button
          asChild
          size="sm"
          data-tour="staff-submit-new"
          className="h-9 text-sm gap-1.5 flex-1 sm:flex-none"
        >
          <Link href={route("staff-dashboard.forms.index")}>
            <Plus className="h-4 w-4" />
            New Request
          </Link>
        </Button>
      </div>
    </div>
  );
}

