import { useState, useEffect } from "react";
import { Bell, Check, X, AlertCircle, ArrowRight } from "lucide-react";
import { Link } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import { formatDistanceToNow } from "date-fns";

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

const priorityConfig = {
  low:    { dot: "bg-sky-400",     iconBg: "bg-sky-50 dark:bg-sky-950/40",         iconColor: "text-sky-600 dark:text-sky-400" },
  normal: { dot: "bg-emerald-400", iconBg: "bg-emerald-50 dark:bg-emerald-950/40", iconColor: "text-emerald-600 dark:text-emerald-400" },
  high:   { dot: "bg-amber-400",   iconBg: "bg-amber-50 dark:bg-amber-950/40",     iconColor: "text-amber-600 dark:text-amber-400" },
  urgent: { dot: "bg-rose-500",    iconBg: "bg-rose-50 dark:bg-rose-950/40",       iconColor: "text-rose-600 dark:text-rose-400" },
};

interface NotificationItemProps {
  notification: NotificationData;
  onRead: (id: number) => void;
  onDelete: (id: number) => void;
}

function NotificationItem({ notification, onRead, onDelete }: NotificationItemProps) {
  const cfg = priorityConfig[notification.priority];

  const handleClick = () => {
    if (!notification.is_read) onRead(notification.id);
    if (notification.action_url) window.location.href = notification.action_url;
  };

  return (
    <div
      role="button"
      tabIndex={0}
      onKeyDown={(e) => e.key === "Enter" && handleClick()}
      onClick={handleClick}
      className={cn(
        "group relative flex items-start gap-3 px-4 py-3 cursor-pointer transition-colors duration-150",
        !notification.is_read ? "bg-primary/[0.03] hover:bg-primary/[0.06]" : "hover:bg-accent/40"
      )}
    >
      {/* Unread strip */}
      {!notification.is_read && (
        <div className="absolute left-0 top-1/4 bottom-1/4 w-0.5 rounded-r bg-primary" />
      )}

      {/* Icon */}
      <span className={cn(
        "flex items-center justify-center w-8 h-8 rounded-lg shrink-0 mt-0.5",
        cfg.iconBg
      )}>
        {notification.priority === "urgent"
          ? <AlertCircle className={cn("h-3.5 w-3.5", cfg.iconColor)} />
          : <Bell className={cn("h-3.5 w-3.5", cfg.iconColor)} />
        }
      </span>

      {/* Body */}
      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-2">
          <p className={cn(
            "text-sm leading-snug text-foreground truncate",
            !notification.is_read ? "font-semibold" : "font-medium"
          )}>
            {notification.title}
          </p>
          <div className="flex items-center gap-1.5 shrink-0">
            {!notification.is_read && (
              <span className="h-1.5 w-1.5 rounded-full bg-primary" />
            )}
            <button
              onClick={(e) => { e.stopPropagation(); onDelete(notification.id); }}
              className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-destructive/10 hover:text-destructive text-muted-foreground"
            >
              <X className="h-3 w-3" />
            </button>
          </div>
        </div>

        <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2 leading-relaxed">
          {notification.message}
        </p>

        <div className="flex items-center justify-between mt-1.5">
          <span className="flex items-center gap-1 text-[10px] text-muted-foreground/70">
            <span className={cn("h-1.5 w-1.5 rounded-full", cfg.dot)} />
            {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
          </span>
          {notification.action_text && (
            <span className="flex items-center gap-0.5 text-[11px] text-primary font-semibold">
              {notification.action_text}
              <ArrowRight className="h-2.5 w-2.5" />
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

export function NotificationBell() {
  const [notifications, setNotifications] = useState<NotificationData[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  const fetchNotifications = async () => {
    try {
      setIsLoading(true);
      const response = await fetch("/api/notifications");
      const data = await response.json();
      setNotifications(data.notifications);
      setUnreadCount(data.unread_count);
    } catch (error) {
      console.error("Failed to fetch notifications:", error);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchNotifications();
    // Poll every 30 seconds
    const interval = setInterval(fetchNotifications, 30000);
    return () => clearInterval(interval);
  }, []);

  const markAsRead = async (id: number) => {
    try {
      await fetch(`/api/notifications/${id}/read`, { 
        method: "POST",
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      });
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, is_read: true } : n))
      );
      setUnreadCount((prev) => Math.max(0, prev - 1));
    } catch (error) {
      console.error("Failed to mark notification as read:", error);
    }
  };

  const markAllAsRead = async () => {
    try {
      await fetch("/api/notifications/mark-all-read", { 
        method: "POST",
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      });
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
      setUnreadCount(0);
    } catch (error) {
      console.error("Failed to mark all as read:", error);
    }
  };

  const deleteNotification = async (id: number) => {
    try {
      await fetch(`/api/notifications/${id}`, { 
        method: "DELETE",
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      });
      setNotifications((prev) => prev.filter((n) => n.id !== id));
      setUnreadCount((prev) => {
        const notification = notifications.find((n) => n.id === id);
        return notification && !notification.is_read ? Math.max(0, prev - 1) : prev;
      });
    } catch (error) {
      console.error("Failed to delete notification:", error);
    }
  };

  return (
    <Popover open={isOpen} onOpenChange={setIsOpen}>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="icon" className="relative h-9 w-9">
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -top-0.5 -right-0.5 h-4 min-w-4 flex items-center justify-center rounded-full bg-primary text-primary-foreground text-[9px] font-bold px-1">
              {unreadCount > 99 ? "99+" : unreadCount}
            </span>
          )}
        </Button>
      </PopoverTrigger>

      <PopoverContent className="w-[380px] p-0 shadow-xl border-border/80" align="end" sideOffset={8}>
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-border/60">
          <div className="flex items-center gap-2">
            <span className="flex items-center justify-center w-7 h-7 rounded-lg bg-primary/10">
              <Bell className="h-3.5 w-3.5 text-primary" />
            </span>
            <div>
              <h3 className="text-sm font-semibold text-foreground leading-tight">Notifications</h3>
              {unreadCount > 0 && (
                <p className="text-[11px] text-muted-foreground">{unreadCount} unread</p>
              )}
            </div>
          </div>
          {unreadCount > 0 && (
            <Button
              variant="ghost"
              size="sm"
              onClick={markAllAsRead}
              className="h-7 text-[11px] gap-1 px-2 text-muted-foreground hover:text-foreground"
            >
              <Check className="h-3 w-3" />
              Mark all read
            </Button>
          )}
        </div>

        {/* Notification list */}
        <ScrollArea className="h-[360px]">
          {isLoading && notifications.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <div className="animate-spin h-5 w-5 border-2 border-primary border-t-transparent rounded-full" />
              <p className="mt-3 text-xs text-muted-foreground">Loading notifications…</p>
            </div>
          ) : notifications.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <span className="flex items-center justify-center w-12 h-12 rounded-xl bg-muted mb-3">
                <Bell className="h-5 w-5 text-muted-foreground/50" />
              </span>
              <p className="text-sm font-medium text-foreground">No notifications</p>
              <p className="text-xs text-muted-foreground mt-0.5">You're all caught up!</p>
            </div>
          ) : (
            <div className="divide-y divide-border/50">
              {notifications.map((notification) => (
                <NotificationItem
                  key={notification.id}
                  notification={notification}
                  onRead={markAsRead}
                  onDelete={deleteNotification}
                />
              ))}
            </div>
          )}
        </ScrollArea>

        {/* Footer */}
        <div className="border-t border-border/60 p-2">
          <Link
            href="/notifications"
            className="flex items-center justify-center gap-1.5 w-full h-8 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-accent/60 transition-colors"
            onClick={() => setIsOpen(false)}
          >
            View all notifications
            <ArrowRight className="h-3 w-3" />
          </Link>
        </div>
      </PopoverContent>
    </Popover>
  );
}
