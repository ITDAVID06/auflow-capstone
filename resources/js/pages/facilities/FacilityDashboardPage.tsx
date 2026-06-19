import { useState, useEffect, useCallback, useMemo, type ReactNode } from "react"
import { Head, Link } from "@inertiajs/react"
import AppLayout from "@/layouts/app-layout"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  Calendar,
  Clock,
  MapPin,
  User,
  FileText,
  ChevronLeft,
  ChevronRight,
  Settings,
  ArrowUpRight,
  FileStack,
  Clock4,
  CheckCircle2,
  XCircle,
} from "lucide-react"
import {
  format,
  addMonths,
  subMonths,
  addWeeks,
  subWeeks,
  startOfMonth,
  endOfMonth,
  startOfWeek,
  endOfWeek,
  eachDayOfInterval,
  getDay,
  addDays,
  subDays,
  isSameDay,
  isSameMonth,
  isToday,
} from "date-fns"
import { toast } from "sonner"

// ─── Types ────────────────────────────────────────────────────────────────────

interface Facility {
  id: number
  name: string
  is_active: boolean
}

interface CalendarEvent {
  id: number
  facilityId: number | null
  facilityName: string
  formType: string
  submissionId: string | number
  requester: string
  title: string
  start: string
  end: string
  status: "Pending" | "Approved" | "Rejected"
}

interface Props {
  facilities: Facility[]
}

// ─── Metric belt ──────────────────────────────────────────────────────────────

const METRIC_CONFIG = [
  {
    key: "total" as const,
    label: "Total Bookings",
    Icon: FileStack,
    valueClass: "text-foreground",
    iconClass: "text-muted-foreground",
  },
  {
    key: "pending" as const,
    label: "Pending",
    Icon: Clock4,
    valueClass: "text-amber-600 dark:text-amber-400",
    iconClass: "text-amber-500 dark:text-amber-400",
  },
  {
    key: "approved" as const,
    label: "Approved",
    Icon: CheckCircle2,
    valueClass: "text-emerald-600 dark:text-emerald-400",
    iconClass: "text-emerald-500 dark:text-emerald-400",
  },
  {
    key: "rejected" as const,
    label: "Rejected",
    Icon: XCircle,
    valueClass: "text-rose-600 dark:text-rose-400",
    iconClass: "text-rose-500 dark:text-rose-400",
  },
]

function metricCellBorder(i: number): string {
  if (i === 1) return "border-l border-border/60"
  if (i === 2) return "border-t border-border/60 sm:border-t-0 sm:border-l"
  if (i === 3) return "border-l border-t border-border/60 sm:border-t-0"
  return ""
}

// ─── Status helpers ───────────────────────────────────────────────────────────

function statusPillClasses(status: string): string {
  const base =
    "w-full cursor-pointer rounded px-2 py-0.5 text-xs leading-none " +
    "inline-flex items-center gap-1.5 transition-colors"
  switch (status) {
    case "Approved":
      return `${base} text-emerald-700 dark:text-emerald-400 bg-emerald-500/10`
    case "Pending":
      return `${base} text-amber-700 dark:text-amber-400 bg-amber-500/10`
    case "Rejected":
      return `${base} text-rose-700 dark:text-rose-400 bg-rose-500/10`
    default:
      return `${base} text-muted-foreground bg-muted/40`
  }
}

function statusDotClasses(status: string): string {
  switch (status) {
    case "Approved": return "bg-emerald-500"
    case "Pending":  return "bg-amber-500"
    case "Rejected": return "bg-rose-500"
    default:         return "bg-muted-foreground/50"
  }
}

function statusBadgeClasses(status: string): string {
  const base = "text-[11px] px-2 py-0.5 rounded font-medium whitespace-nowrap"
  switch (status) {
    case "Approved":
      return `${base} text-emerald-700 dark:text-emerald-400 bg-emerald-500/10`
    case "Pending":
      return `${base} text-amber-700 dark:text-amber-400 bg-amber-500/10`
    case "Rejected":
      return `${base} text-rose-700 dark:text-rose-400 bg-rose-500/10`
    default:
      return `${base} text-muted-foreground bg-muted/50`
  }
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function FacilityDashboardPage({ facilities }: Props) {
  const [events, setEvents] = useState<CalendarEvent[]>([])
  const [selectedFacility, setSelectedFacility] = useState<string>("all")
  const [viewMode, setViewMode] = useState<"month" | "week">("month")
  const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null)
  const [selectedDate, setSelectedDate] = useState<Date | null>(null)
  const [selectedDateEvents, setSelectedDateEvents] = useState<CalendarEvent[]>([])
  const [isEventPopoverOpen, setIsEventPopoverOpen] = useState(false)
  const [isDayEventsOpen, setIsDayEventsOpen] = useState(false)
  const [currentDate, setCurrentDate] = useState<Date>(new Date())
  const [eventsLoading, setEventsLoading] = useState(false)

  const fetchEvents = useCallback(async () => {
    setEventsLoading(true)
    try {
      const periodStart =
        viewMode === "week"
          ? startOfWeek(currentDate, { weekStartsOn: 0 })
          : startOfMonth(currentDate)
      const periodEnd =
        viewMode === "week"
          ? endOfWeek(currentDate, { weekStartsOn: 0 })
          : endOfMonth(currentDate)

      const params = new URLSearchParams()
      params.append("start", format(periodStart, "yyyy-MM-dd"))
      params.append("end", format(periodEnd, "yyyy-MM-dd"))
      if (selectedFacility !== "all") params.append("facility_id", selectedFacility)

      const res = await fetch(`/admin/facilities/calendar/events?${params.toString()}`)
      const data = await res.json()
      setEvents(Array.isArray(data) ? data : [])
    } catch {
      toast.error("Failed to load events")
    } finally {
      setEventsLoading(false)
    }
  }, [currentDate, selectedFacility, viewMode])

  useEffect(() => {
    fetchEvents()
  }, [fetchEvents])

  const metrics = useMemo(
    () => ({
      total: events.length,
      approved: events.filter((e) => e.status === "Approved").length,
      pending: events.filter((e) => e.status === "Pending").length,
      rejected: events.filter((e) => e.status === "Rejected").length,
    }),
    [events],
  )

  const weekStart = startOfWeek(currentDate, { weekStartsOn: 0 })
  const weekEnd = endOfWeek(currentDate, { weekStartsOn: 0 })
  const monthStart = startOfMonth(currentDate)
  const monthEnd = endOfMonth(currentDate)
  const monthGridStart = subDays(monthStart, getDay(monthStart))
  const monthGridEnd = addDays(monthEnd, 6 - getDay(monthEnd))

  const calendarDays =
    viewMode === "week"
      ? eachDayOfInterval({ start: weekStart, end: weekEnd })
      : eachDayOfInterval({ start: monthGridStart, end: monthGridEnd })

  const getEventsForDate = (date: Date) =>
    events.filter((event) => isSameDay(new Date(event.start), date))

  const sortedSelectedDateEvents = useMemo(
    () =>
      [...selectedDateEvents].sort(
        (a, b) => new Date(a.start).getTime() - new Date(b.start).getTime(),
      ),
    [selectedDateEvents],
  )

  const upcomingEvents = useMemo(() => {
    const now = new Date()
    return events
      .filter((e) => e.status === "Approved" && new Date(e.end) >= now)
      .sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime())
      .slice(0, 5)
  }, [events])

  const handleDateClick = (date: Date) => {
    setSelectedDate(date)
    setSelectedDateEvents(getEventsForDate(date))
    setIsDayEventsOpen(true)
  }

  const navigatePeriod = (dir: "prev" | "next") => {
    if (viewMode === "week") {
      setCurrentDate(dir === "prev" ? subWeeks(currentDate, 1) : addWeeks(currentDate, 1))
      return
    }
    setCurrentDate(dir === "prev" ? subMonths(currentDate, 1) : addMonths(currentDate, 1))
  }

  const periodLabel =
    viewMode === "week"
      ? `${format(weekStart, "MMM d")} \u2013 ${format(weekEnd, "MMM d, yyyy")}`
      : format(currentDate, "MMMM yyyy")

  return (
    <>
      <Head title="Facility Dashboard" />

      <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        {/* Metric Belt */}
        <dl
          className="grid grid-cols-2 overflow-hidden rounded-lg border border-border/60 bg-card sm:grid-cols-4"
          aria-label="Booking metrics for current period"
        >
          {METRIC_CONFIG.map((m, i) => {
            const Icon = m.Icon
            return (
              <div key={m.key} className={`flex flex-col gap-1.5 px-5 py-5 ${metricCellBorder(i)}`}>
                <dt className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                  <Icon className={`h-3.5 w-3.5 ${m.iconClass}`} aria-hidden="true" />
                  {m.label}
                </dt>
                <dd className={`text-3xl font-semibold tabular-nums leading-none ${m.valueClass}`}>
                  {eventsLoading ? (
                    <span className="inline-block h-8 w-12 animate-pulse rounded bg-muted" />
                  ) : (
                    metrics[m.key].toLocaleString()
                  )}
                </dd>
              </div>
            )
          })}
        </dl>

        {/* Toolbar */}
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="text-base font-semibold text-foreground tabular-nums">{periodLabel}</h2>

          <div className="ml-1 inline-flex items-center gap-1">
            <Button
              variant="outline"
              size="sm"
              onClick={() => navigatePeriod("prev")}
              aria-label="Previous period"
              className="h-8 w-8 p-0 touch-manipulation"
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setCurrentDate(new Date())}
              className="h-8 px-3 touch-manipulation"
            >
              Today
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => navigatePeriod("next")}
              aria-label="Next period"
              className="h-8 w-8 p-0 touch-manipulation"
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>

          <div className="inline-flex items-center rounded-md border border-border/60 bg-card p-0.5">
            <Button
              type="button"
              size="sm"
              variant={viewMode === "month" ? "default" : "ghost"}
              className="h-7 rounded-sm px-3 text-xs"
              onClick={() => setViewMode("month")}
            >
              Month
            </Button>
            <Button
              type="button"
              size="sm"
              variant={viewMode === "week" ? "default" : "ghost"}
              className="h-7 rounded-sm px-3 text-xs"
              onClick={() => setViewMode("week")}
            >
              Week
            </Button>
          </div>

          <Select value={selectedFacility} onValueChange={setSelectedFacility}>
            <SelectTrigger className="h-8 w-44 text-xs">
              <SelectValue placeholder="All Facilities" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Facilities</SelectItem>
              {facilities.map((f) => (
                <SelectItem key={f.id} value={String(f.id)}>{f.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>

          <div className="ml-auto flex items-center gap-2">
            <Link
              href="/admin/facilities/upcoming-events"
              className="inline-flex h-8 items-center gap-1.5 rounded-md border border-border/60 bg-card px-3 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors touch-manipulation"
            >
              <Calendar className="h-3.5 w-3.5" />
              Upcoming Events
              <ArrowUpRight className="h-3 w-3" />
            </Link>
            <Link
              href="/admin/facilities"
              className="inline-flex h-8 items-center gap-1.5 rounded-md border border-border/60 bg-card px-3 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors touch-manipulation"
            >
              <Settings className="h-3.5 w-3.5" />
              Manage Facilities
            </Link>
          </div>
        </div>

        {/* Main content */}
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_240px]">

          {/* Calendar grid */}
          <div className="relative overflow-hidden rounded-lg border border-border/60 bg-card">
            {eventsLoading && (
              <div className="absolute inset-0 z-10 flex items-start justify-center bg-card/60 pt-16 backdrop-blur-[1px]">
                <div
                  className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent"
                  aria-label="Loading events"
                />
              </div>
            )}

            {/* Day headers */}
            <div className="grid grid-cols-7 divide-x divide-border/50 border-b border-border/60 bg-muted/40">
              {["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].map((day) => (
                <div
                  key={day}
                  className="px-2 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground"
                >
                  {day}
                </div>
              ))}
            </div>

            {/* Calendar days */}
            <div className="grid grid-cols-7 divide-x divide-y divide-border/40">
              {calendarDays.map((day) => {
                const dayEvents = getEventsForDate(day)
                const isCurrentMonth = isSameMonth(day, currentDate)
                const isDayToday = isToday(day)

                return (
                  <div
                    key={day.toISOString()}
                    role="button"
                    tabIndex={0}
                    aria-label={`${format(day, "MMMM d, yyyy")} \u2013 ${dayEvents.length} booking${dayEvents.length !== 1 ? "s" : ""}`}
                    onKeyDown={(e) =>
                      (e.key === "Enter" || e.key === " ") && handleDateClick(day)
                    }
                    className={[
                      "min-h-[110px] cursor-pointer p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring",
                      isCurrentMonth ? "bg-card" : "bg-muted/20",
                      "hover:bg-accent/30",
                    ].join(" ")}
                    onClick={() => handleDateClick(day)}
                  >
                    <div className="mb-1 flex justify-end">
                      <span
                        className={[
                          "inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-medium",
                          isDayToday
                            ? "bg-primary text-primary-foreground"
                            : isCurrentMonth
                              ? "text-foreground/80"
                              : "text-muted-foreground/50",
                        ].join(" ")}
                      >
                        {day.getDate()}
                      </span>
                    </div>

                    <div className="space-y-0.5">
                      {dayEvents.slice(0, 3).map((event) => (
                        <Popover
                          key={event.id}
                          open={isEventPopoverOpen && selectedEvent?.id === event.id}
                          onOpenChange={setIsEventPopoverOpen}
                        >
                          <PopoverTrigger asChild>
                            <div
                              onClick={(e) => {
                                e.stopPropagation()
                                setSelectedEvent(event)
                                setIsEventPopoverOpen(true)
                              }}
                              className={statusPillClasses(event.status)}
                              title={`${format(new Date(event.start), "HH:mm")} ${event.title}`}
                            >
                              <span className={["h-1.5 w-1.5 rounded-full shrink-0", statusDotClasses(event.status)].join(" ")} />
                              <span className="truncate font-medium">{event.title}</span>
                            </div>
                          </PopoverTrigger>

                          <PopoverContent className="w-72 p-0" align="start" sideOffset={6}>
                            <div className="p-4">
                              <div className="flex items-start justify-between gap-2 mb-3">
                                <p className="text-sm font-semibold text-foreground leading-tight">
                                  {event.title}
                                </p>
                                <span className={statusBadgeClasses(event.status)}>{event.status}</span>
                              </div>
                              <div className="space-y-1.5 text-xs text-muted-foreground">
                                <div className="flex items-center gap-1.5">
                                  <Clock className="h-3 w-3 shrink-0" />
                                  <span>{format(new Date(event.start), "MMM d, HH:mm")} &ndash; {format(new Date(event.end), "HH:mm")}</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                  <MapPin className="h-3 w-3 shrink-0" />
                                  <span className="truncate">{event.facilityName}</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                  <User className="h-3 w-3 shrink-0" />
                                  <span className="truncate">{event.requester}</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                  <FileText className="h-3 w-3 shrink-0" />
                                  <span className="truncate">{event.formType} #{event.submissionId}</span>
                                </div>
                              </div>
                            </div>
                          </PopoverContent>
                        </Popover>
                      ))}
                      {dayEvents.length > 3 && (
                        <p className="px-1 text-[11px] text-muted-foreground/70">
                          +{dayEvents.length - 3} more
                        </p>
                      )}
                    </div>
                  </div>
                )
              })}
            </div>
          </div>

          {/* Right sidebar */}
          <div>
            <div className="rounded-lg border border-border/60 bg-card">
              <div className="flex items-center justify-between px-4 pt-4 pb-3 border-b border-border/50">
                <h3 className="text-sm font-semibold text-foreground">Upcoming</h3>
                <Link
                  href="/admin/facilities/upcoming-events"
                  className="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1 transition-colors"
                >
                  View all
                  <ArrowUpRight className="h-3 w-3" />
                </Link>
              </div>

              <div className="divide-y divide-border/40">
                {upcomingEvents.length === 0 ? (
                  <p className="px-4 py-4 text-xs text-muted-foreground">
                    {eventsLoading ? "Loading\u2026" : "No upcoming approved bookings."}
                  </p>
                ) : (
                  upcomingEvents.map((event) => (
                    <button
                      key={`upcoming-${event.id}`}
                      type="button"
                      onClick={() => handleDateClick(new Date(event.start))}
                      className="flex w-full items-start gap-2.5 px-4 py-3 text-left transition-colors hover:bg-accent/30 touch-manipulation"
                    >
                      <span className="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded bg-muted text-muted-foreground">
                        <Calendar className="h-3 w-3" />
                      </span>
                      <span className="min-w-0">
                        <span className="block truncate text-xs font-medium text-foreground">{event.title}</span>
                        <span className="block text-[11px] text-muted-foreground tabular-nums">
                          {format(new Date(event.start), "MMM d \u00b7 h:mm a")}
                        </span>
                        <span className="block truncate text-[11px] text-muted-foreground">{event.facilityName}</span>
                      </span>
                    </button>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Day Events Modal */}
      <Dialog open={isDayEventsOpen} onOpenChange={setIsDayEventsOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>
              {selectedDate ? format(selectedDate, "EEEE, MMMM d, yyyy") : "Selected date"}
            </DialogTitle>
            <DialogDescription>
              {selectedDateEvents.length}{" "}
              {selectedDateEvents.length === 1 ? "booking" : "bookings"} on this day
            </DialogDescription>
          </DialogHeader>

          <div className="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
            {selectedDateEvents.length === 0 ? (
              <div className="rounded-md border border-border/50 bg-muted/30 px-4 py-5 text-sm text-center text-muted-foreground">
                No bookings for this date.
              </div>
            ) : (
              sortedSelectedDateEvents.map((event) => (
                <div key={event.id} className="rounded-md border border-border/60 bg-card px-4 py-3">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-semibold text-foreground">{event.title}</p>
                      <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                        {format(new Date(event.start), "h:mm a")} &ndash;{" "}
                        {format(new Date(event.end), "h:mm a")}
                      </p>
                    </div>
                    <span className={statusBadgeClasses(event.status)}>{event.status}</span>
                  </div>

                  <div className="mt-2 space-y-1 text-xs text-muted-foreground">
                    <div className="flex items-center gap-1.5">
                      <MapPin className="h-3 w-3 shrink-0" />
                      <span className="truncate">{event.facilityName}</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <User className="h-3 w-3 shrink-0" />
                      <span className="truncate">{event.requester}</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <FileText className="h-3 w-3 shrink-0" />
                      <span className="truncate">{event.formType} #{event.submissionId}</span>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}

;(
  FacilityDashboardPage as typeof FacilityDashboardPage & {
    layout?: (page: ReactNode) => ReactNode
  }
).layout = (page: ReactNode) => (
  <AppLayout title="Facility Dashboard" subtitle="Manage facility bookings and availability">
    {page}
  </AppLayout>
)
