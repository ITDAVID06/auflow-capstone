import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Breadcrumbs } from "@/components/breadcrumbs";
import { Bell, Check, Trash2, AlertCircle, ArrowRight, BellOff, ChevronLeft, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { formatDistanceToNow } from "date-fns";
import { useState } from "react";
import { router } from "@inertiajs/react";

const PAGE_SIZE = 10;

interface NotificationData {
  id: number;
  type: string;
  title: string;
  message: string;
  action_url?: string;
  action_text?: string;
  icon: string;
  priority: "low" | "normal" | "high" | "urgent";
  is_read: boolean;
  created_at: string;
  triggered_by?: {
    account_id: number;
    username: string;
    full_name: string;
  };
}

interface NotificationsPageProps {
  notifications: NotificationData[];
  unread_count: number;
}

const priorityConfig = {
  low:    { dot: "bg-sky-400",     label: "Low",    iconBg: "bg-sky-50 dark:bg-sky-950/40",     iconColor: "text-sky-600 dark:text-sky-400" },
  normal: { dot: "bg-emerald-400", label: "Normal", iconBg: "bg-emerald-50 dark:bg-emerald-950/40", iconColor: "text-emerald-600 dark:text-emerald-400" },
  high:   { dot: "bg-amber-400",   label: "High",   iconBg: "bg-amber-50 dark:bg-amber-950/40", iconColor: "text-amber-600 dark:text-amber-400" },
  urgent: { dot: "bg-rose-500",    label: "Urgent", iconBg: "bg-rose-50 dark:bg-rose-950/40",   iconColor: "text-rose-600 dark:text-rose-400" },
};

export default function NotificationsPage({ notifications: initialNotifications, unread_count }: NotificationsPageProps) {
  const [notifications, setNotifications] = useState(initialNotifications);
  const [localUnread, setLocalUnread] = useState(unread_count);
  const [filter, setFilter] = useState<"all" | "unread">("all");
  const [currentPage, setCurrentPage] = useState(1);

  const filteredNotifications =
    filter === "unread"
      ? notifications.filter((n) => !n.is_read)
      : notifications;

  const totalPages = Math.max(1, Math.ceil(filteredNotifications.length / PAGE_SIZE));
  const paginatedNotifications = filteredNotifications.slice(
    (currentPage - 1) * PAGE_SIZE,
    currentPage * PAGE_SIZE
  );

  const handleFilterChange = (tab: "all" | "unread") => {
    setFilter(tab);
    setCurrentPage(1);
  };

  const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

  const markAsRead = async (id: number) => {
    try {
      await fetch(`/api/notifications/${id}/read`, {
        method: "POST",
        headers: { "X-CSRF-TOKEN": csrfToken(), "Content-Type": "application/json", Accept: "application/json" },
      });
      setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, is_read: true } : n)));
      setLocalUnread((c) => Math.max(0, c - 1));
    } catch { /* silent */ }
  };

  const markAllAsRead = async () => {
    try {
      await fetch("/api/notifications/mark-all-read", {
        method: "POST",
        headers: { "X-CSRF-TOKEN": csrfToken(), "Content-Type": "application/json", Accept: "application/json" },
      });
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
      setLocalUnread(0);
    } catch { /* silent */ }
  };

  const deleteNotification = async (id: number) => {
    const target = notifications.find((n) => n.id === id);
    try {
      await fetch(`/api/notifications/${id}`, {
        method: "DELETE",
        headers: { "X-CSRF-TOKEN": csrfToken(), "Content-Type": "application/json", Accept: "application/json" },
      });
      setNotifications((prev) => prev.filter((n) => n.id !== id));
      if (target && !target.is_read) setLocalUnread((c) => Math.max(0, c - 1));
    } catch { /* silent */ }
  };

  const handleNotificationClick = (notification: NotificationData) => {
    if (!notification.is_read) markAsRead(notification.id);
    if (notification.action_url) router.visit(notification.action_url);
  };

  return (
    <AppLayout
      title="Notifications"
      subtitle={
        localUnread > 0
          ? `${localUnread} unread notification${localUnread !== 1 ? "s" : ""}`
          : "All caught up"
      }
    >
      <div className="mx-auto w-full max-w-3xl px-4 py-5 sm:px-6 sm:py-6 space-y-5">

        {/* ── Breadcrumbs ───────────────────────────────────────────── */}
        <Breadcrumbs breadcrumbs={[
          { title: "Dashboard", href: "/" },
          { title: "Notifications", href: "/notifications" },
        ]} />

        {/* ── Filter tabs + Mark All ───────────────────────────────── */}
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-center gap-1 p-1 bg-muted/60 rounded-lg">
            {(["all", "unread"] as const).map((tab) => {
              const count = tab === "all" ? notifications.length : localUnread;
              return (
                <button
                  key={tab}
                  onClick={() => handleFilterChange(tab)}
                  className={cn(
                    "flex items-center gap-1.5 px-3.5 py-1.5 rounded-md text-sm font-medium motion-safe:transition-colors",
                    filter === tab
                      ? "bg-primary text-primary-foreground"
                      : "text-muted-foreground hover:text-foreground"
                  )}
                >
                  {tab === "all" ? "All" : "Unread"}
                  <span className={cn(
                    "inline-flex items-center justify-center min-w-[18px] h-[18px] rounded-full text-[10px] font-semibold px-1",
                    filter === tab ? "bg-white/20 text-primary-foreground" : "bg-muted text-muted-foreground"
                  )}>
                    {count}
                  </span>
                </button>
              );
            })}
          </div>

          {localUnread > 0 && (
            <Button onClick={markAllAsRead} variant="outline" size="sm" className="gap-1.5 text-xs h-8">
              <Check className="h-3.5 w-3.5" />
              Mark All as Read
            </Button>
          )}
        </div>

        {/* ── Notification list ─────────────────────────────────────── */}
        {filteredNotifications.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-center bg-card border border-border/50 rounded-xl">
            <span className="flex items-center justify-center w-14 h-14 rounded-xl bg-muted mb-4">
              <BellOff className="h-6 w-6 text-muted-foreground/60" />
            </span>
            <p className="text-sm font-semibold text-foreground mb-1">
              No {filter === "unread" ? "unread " : ""}notifications
            </p>
            <p className="text-xs text-muted-foreground max-w-xs">
              {filter === "unread"
                ? "You're all caught up. No unread notifications."
                : "You haven't received any notifications yet."}
            </p>
          </div>
        ) : (
          <div className="bg-card border border-border/50 rounded-xl overflow-hidden divide-y divide-border/50">
            {paginatedNotifications.map((notification) => {
              const cfg = priorityConfig[notification.priority];
              return (
                <div
                  key={notification.id}
                  role="button"
                  tabIndex={0}
                  onKeyDown={(e) => e.key === "Enter" && handleNotificationClick(notification)}
                  onClick={() => handleNotificationClick(notification)}
                  className={cn(
                    "group relative flex items-start gap-4 px-5 py-4 cursor-pointer motion-safe:transition-colors",
                    !notification.is_read
                      ? "bg-primary/[0.03] hover:bg-primary/[0.06]"
                      : "hover:bg-accent/40"
                  )}
                >
                  {/* Unread indicator strip */}
                  {!notification.is_read && (
                    <div className="absolute left-0 top-1/4 bottom-1/4 w-0.5 rounded-r bg-primary" />
                  )}

                  {/* Icon */}
                  <span className={cn(
                    "flex items-center justify-center w-9 h-9 rounded-lg shrink-0 mt-0.5",
                    cfg.iconBg
                  )}>
                    {notification.priority === "urgent"
                      ? <AlertCircle className={cn("h-4 w-4", cfg.iconColor)} />
                      : <Bell className={cn("h-4 w-4", cfg.iconColor)} />
                    }
                  </span>

                  {/* Body */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className={cn(
                          "text-sm leading-snug text-foreground truncate",
                          !notification.is_read ? "font-semibold" : "font-medium"
                        )}>
                          {notification.title}
                        </p>
                        {notification.triggered_by && (
                          <p className="text-[11px] text-muted-foreground mt-0.5">
                            by {notification.triggered_by.full_name}
                          </p>
                        )}
                      </div>

                      {/* Priority + unread dot */}
                      <div className="flex items-center gap-2 shrink-0">
                        <span className="flex items-center gap-1 text-[11px] text-muted-foreground font-medium">
                          <span className={cn("h-1.5 w-1.5 rounded-full", cfg.dot)} />
                          {cfg.label}
                        </span>
                        {!notification.is_read && (
                          <span className="h-2 w-2 rounded-full bg-primary shrink-0" />
                        )}
                      </div>
                    </div>

                    <p className="text-xs text-muted-foreground mt-1.5 leading-relaxed line-clamp-2">
                      {notification.message}
                    </p>

                    <div className="flex items-center justify-between mt-2.5">
                      <span className="text-[11px] text-muted-foreground/70">
                        {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                      </span>

                      <div className="flex items-center gap-1">
                        {notification.action_text && (
                          <span className="flex items-center gap-0.5 text-[11px] text-primary font-semibold group-hover:underline underline-offset-2">
                            {notification.action_text}
                            <ArrowRight className="h-3 w-3" />
                          </span>
                        )}

                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7 opacity-0 group-hover:opacity-100 motion-safe:transition-opacity hover:text-destructive hover:bg-destructive/10"
                          onClick={(e) => {
                            e.stopPropagation();
                            deleteNotification(notification.id);
                          }}
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* ── Pagination ────────────────────────────────────────────── */}
        {totalPages > 1 && (
          <div className="flex items-center justify-between">
            <p className="text-xs text-muted-foreground">
              Showing {(currentPage - 1) * PAGE_SIZE + 1}–{Math.min(currentPage * PAGE_SIZE, filteredNotifications.length)} of {filteredNotifications.length}
            </p>
            <div className="flex items-center gap-1">
              <Button
                variant="outline"
                size="icon"
                className="h-8 w-8"
                disabled={currentPage === 1}
                onClick={() => setCurrentPage((p) => p - 1)}
              >
                <ChevronLeft className="h-4 w-4" />
              </Button>

              {Array.from({ length: totalPages }, (_, i) => i + 1)
                .filter((p) => p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1)
                .reduce<(number | "...")[]>((acc, p, idx, arr) => {
                  if (idx > 0 && typeof arr[idx - 1] === "number" && (p as number) - (arr[idx - 1] as number) > 1) {
                    acc.push("...");
                  }
                  acc.push(p);
                  return acc;
                }, [])
                .map((item, idx) =>
                  item === "..." ? (
                    <span key={`ellipsis-${idx}`} className="px-1 text-xs text-muted-foreground">&hellip;</span>
                  ) : (
                    <Button
                      key={item}
                      variant="outline"
                      size="icon"
                      className={cn(
                        "h-8 w-8 text-xs",
                        currentPage === item && "bg-accent text-foreground border-border/80"
                      )}
                      onClick={() => setCurrentPage(item as number)}
                    >
                      {item}
                    </Button>
                  )
                )
              }

              <Button
                variant="outline"
                size="icon"
                className="h-8 w-8"
                disabled={currentPage === totalPages}
                onClick={() => setCurrentPage((p) => p + 1)}
              >
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        )}

      </div>
    </AppLayout>
  );
}
