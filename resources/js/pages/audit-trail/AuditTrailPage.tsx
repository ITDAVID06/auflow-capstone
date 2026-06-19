import { useEffect, useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import Pagination from "@/components/shared/Pagination";
import { toast } from "sonner";
import { CheckCircle2, AlertTriangle, LogIn, LogOut, QrCode, ScrollText } from "lucide-react";

type LogItem = {
  id: number;
  category: "user_action" | "system_event" | "security";
  action: string;
  status?: string;
  description?: string;
  actor?: { id?: number; name?: string; email?: string; role?: string };
  auditable?: { type?: string; id?: number } | null;
  meta?: Record<string, unknown>;
  ref?: string;
  ip?: string;
  qr?: { result?: string; payload?: string };
  created_at: string;
};

interface PaginationMeta {
  current_page: number;
  last_page: number;
  total: number;
}

export default function AuditTrailPage() {
  // Only keep the two tabs requested
  const [tab, setTab] = useState<"user_action" | "security">("user_action");
  const [items, setItems] = useState<LogItem[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [loading, setLoading] = useState(false);

  const fetchData = async (page = 1) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set("category", tab);
      params.set("page", page.toString());
      params.set("per_page", "10");

      const res = await fetch(route("audit.data") + "?" + params.toString(), {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const body = await res.json();

      setItems(body.data ?? []);
      if (body.meta) {
        setMeta({
          current_page: body.meta.current_page,
          last_page: body.meta.last_page,
          total: body.meta.total,
        });
      }
    } catch {
      toast.error("Failed to load audit logs");
    } finally {
      setLoading(false);
    }
  };

  // Fetch whenever tab changes; clear stale items immediately to avoid flash of wrong data
  useEffect(() => {
    setItems([]);
    fetchData();
  }, [tab]); // eslint-disable-line react-hooks/exhaustive-deps

  const exportCsv = () => {
    const params = new URLSearchParams();
    params.set("category", tab);
    const a = document.createElement("a");
    a.href = route("audit.export") + "?" + params.toString();
    a.download = "";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

  function statusClasses(status?: string) {
    const styles = {
      chip: "bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400",
      iconWrap: "bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400",
    };
    if (!status) return styles;
    const s = status.toLowerCase();
    if (s.includes("success") || s.includes("verified")) {
      return {
        chip: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400",
        iconWrap: "bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400",
      };
    }
    if (s.includes("warning")) {
      return {
        chip: "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400",
        iconWrap: "bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400",
      };
    }
    if (s.includes("failed") || s.includes("reject")) {
      return {
        chip: "bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400",
        iconWrap: "bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400",
      };
    }
    return styles;
  }

  function titleFor(item: LogItem) {
    if (item.category === "security") {
      if (item.action === "login_success") return "Successful Login";
      if (item.action === "login_failed") return "Failed Login Attempt";
      if (item.action === "logout") return "User logged out";
      if (item.action === "qr_verification") return "QR/Snapshot Verification";
    }
    return (item.description || item.action).replaceAll("_", " ");
  }

  function iconFor(item: LogItem) {
    if (item.category === "security") {
      if (item.action === "login_success") return LogIn;
      if (item.action === "login_failed") return AlertTriangle;
      if (item.action === "logout") return LogOut;
      if (item.action === "qr_verification") return QrCode;
    }
    return item.status === "Success" || item.status === "Verified" ? CheckCircle2 : AlertTriangle;
  }

  function refLabel(item: LogItem): string | undefined {
    const m = item.meta ?? {};
    const first =
      m.ref ||
      m.form_name ||
      m.field_label ||
      m.role_name ||
      m.permission_name ||
      m.workflow_name ||
      m.step_name;
    if (first) return String(first);
    if (m.role_name && m.permission_name) {
      return `Role ${m.role_name} · Permission ${m.permission_name}`;
    }
    if (m.user_name && m.role_name) {
      return `${m.user_name} · ${m.role_name}`;
    }
    return undefined;
  }

  return (
      <AppLayout title="Audit Trail" subtitle="Track user actions and system security events.">

        <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
          <Tabs value={tab} onValueChange={(v) => setTab(v as "user_action" | "security")} className="space-y-0" data-tour="audit-tabs">
            <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-800">
              <TabsList className="h-auto gap-0 rounded-none border-0 bg-transparent p-0">
                <TabsTrigger
                  value="user_action"
                  className="h-10 -mb-px rounded-none border-b-2 border-transparent px-4 text-sm font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 data-[state=active]:border-gray-900 data-[state=active]:text-gray-900 dark:data-[state=active]:border-gray-100 dark:data-[state=active]:text-gray-100 data-[state=active]:shadow-none focus-visible:ring-0 motion-safe:transition-colors"
                >
                  User Actions
                </TabsTrigger>
                <TabsTrigger
                  value="security"
                  className="h-10 -mb-px rounded-none border-b-2 border-transparent px-4 text-sm font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 data-[state=active]:border-gray-900 data-[state=active]:text-gray-900 dark:data-[state=active]:border-gray-100 dark:data-[state=active]:text-gray-100 data-[state=active]:shadow-none focus-visible:ring-0 motion-safe:transition-colors"
                >
                  Security Logs
                </TabsTrigger>
              </TabsList>

              <div data-tour="audit-export">
                <Button variant="outline" size="sm" onClick={exportCsv}>
                  Export CSV
                </Button>
              </div>
            </div>

          {(["user_action", "security"] as const).map((key) => (
            <TabsContent key={key} value={key} className="mt-0">
              <div className="divide-y divide-gray-100 dark:divide-gray-700/60" aria-live="polite" aria-busy={loading}>
                {/* Skeleton */}
                {loading &&
                  Array.from({ length: 10 }).map((_, i) => (
                    <div key={`skeleton-${i}`} className="flex items-start gap-3 px-4 py-3 animate-pulse">
                      <div className="mt-0.5 h-7 w-7 flex-shrink-0 rounded-md bg-gray-200 dark:bg-gray-800" />
                      <div className="flex-1 space-y-2 pt-0.5">
                        <div className="flex items-center gap-2">
                          <div className="h-4 w-48 rounded bg-gray-200 dark:bg-gray-800" />
                          <div className="h-4 w-14 rounded-md bg-gray-200 dark:bg-gray-800" />
                        </div>
                        <div className="h-3 w-64 rounded bg-gray-200 dark:bg-gray-800" />
                      </div>
                    </div>
                  ))}

                {/* Rows */}
                {!loading &&
                  items.map((item, idx) => {
                    const derivedRef = refLabel(item);
                    const Icon = iconFor(item);
                    const cls = statusClasses(item.status);

                    const metaPrimary = item.actor?.name
                      ? `${item.actor.name}${item.actor.role ? ` · ${item.actor.role}` : ""}`
                      : "System";

                    const metaSecondaryParts: string[] = [item.created_at];
                    if (item.ip) metaSecondaryParts.push(`IP ${item.ip}`);
                    if (derivedRef) metaSecondaryParts.push(derivedRef);

                    return (
                      <div
                        key={item.id}
                        className="flex items-start gap-3 px-4 py-3 motion-safe:transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        data-tour={idx === 0 ? "audit-table" : undefined}
                      >
                        {/* Icon */}
                        <div className={`mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md ${cls.iconWrap}`}>
                          <Icon className="h-3.5 w-3.5" />
                        </div>

                        {/* Content */}
                        <div className="flex-1 min-w-0">
                          {/* Title + status inline */}
                          <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span className="text-sm font-medium leading-snug text-gray-900 dark:text-gray-100">{titleFor(item)}</span>
                            <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-none ${cls.chip}`}>
                              {item.status ?? "Info"}
                            </span>
                          </div>

                          {/* Primary meta: actor */}
                          <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">{metaPrimary}</div>

                          {/* Secondary meta: time · IP · ref */}
                          <div className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                            {metaSecondaryParts.join(" · ")}
                          </div>

                          {/* Description — subordinated */}
                          {item.description && (
                            <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">{item.description}</div>
                          )}

                          {/* QR verification detail */}
                          {item.category === "security" && (item.qr?.result || item.qr?.payload) && (
                            <div className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                              Verification: {item.qr?.result ?? "—"}
                              {item.qr?.payload && <> · QR: {item.qr.payload}</>}
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}

                {/* Empty state */}
                {!loading && items.length === 0 && (
                  <div className="flex flex-col items-center py-16 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-800 mb-4">
                      <ScrollText className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                    </div>
                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">No logs found</p>
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-[240px] leading-relaxed">
                      No entries for this category yet. Try switching tabs or check back later.
                    </p>
                  </div>
                )}
              </div>

              <div className="mt-4 px-4" data-tour="audit-pagination">
                <Pagination
                  currentPage={meta?.current_page ?? 1}
                  lastPage={meta?.last_page ?? 1}
                  currentCount={items.length}
                  total={meta?.total ?? 0}
                  itemLabel="logs"
                  alwaysShow
                  onPageChange={fetchData}
                />
              </div>
            </TabsContent>
          ))}
        </Tabs>
      </div>
    </AppLayout>
  );
}
