import React, { useState } from "react"
import AppLayout from "@/layouts/app-layout"
import { Head, Link, router } from "@inertiajs/react"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Button } from "@/components/ui/button"
import {
  Calendar,
  Clock,
  MapPin,
  User,
  FileText,
  ChevronLeft,
  ChevronRight,
  CheckCircle2,
} from "lucide-react"
import { format, parseISO } from "date-fns"

// ─── Types ────────────────────────────────────────────────────────────────────

interface UpcomingEvent {
  id: number
  facilityId: number | null
  facilityName: string
  formType: string
  submissionId: number
  requester: string
  title: string
  date: string
  startTime: string | null
  endTime: string | null
}

interface PaginatedEvents {
  data: UpcomingEvent[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface Facility {
  id: number
  name: string
}

interface Props {
  events: PaginatedEvents
  facilities: Facility[]
  filters: {
    facility_id?: string | null
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatTime(time: string | null): string {
  if (!time) return ""
  const [h, m] = time.split(":")
  const hours = parseInt(h, 10)
  const minutes = m ?? "00"
  const period = hours >= 12 ? "PM" : "AM"
  const displayHour = hours % 12 === 0 ? 12 : hours % 12
  return `${displayHour}:${minutes} ${period}`
}

function formatDateHeading(dateStr: string): string {
  return format(parseISO(dateStr), "EEEE, MMMM d, yyyy")
}

function formatDateShort(dateStr: string): string {
  return format(parseISO(dateStr), "MMM d, yyyy")
}

// Group events by date for display
function groupByDate(events: UpcomingEvent[]): { date: string; items: UpcomingEvent[] }[] {
  const map = new Map<string, UpcomingEvent[]>()
  for (const event of events) {
    const existing = map.get(event.date) ?? []
    existing.push(event)
    map.set(event.date, existing)
  }
  return Array.from(map.entries()).map(([date, items]) => ({ date, items }))
}

// ─── Page ─────────────────────────────────────────────────────────────────────

const FacilityUpcomingEventsPage: React.FC<Props> = ({ events, facilities, filters }) => {
  const [selectedFacility, setSelectedFacility] = useState(filters.facility_id ?? "all")

  const { data: eventList } = events
  const grouped = groupByDate(eventList)

  const handleFacilityChange = (value: string) => {
    setSelectedFacility(value)
    router.get(
      "/admin/facilities/upcoming-events",
      { facility_id: value === "all" ? undefined : value },
      { preserveState: true, replace: true },
    )
  }

  const goToPage = (page: number) => {
    router.get(
      "/admin/facilities/upcoming-events",
      {
        facility_id: selectedFacility === "all" ? undefined : selectedFacility,
        page,
      },
      { preserveState: true, replace: true },
    )
  }

  return (
    <>
      <Head title="Upcoming Events" />

      <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        {/* Back link */}
        <div>
          <Link
            href="/admin/facilities/calendar"
            className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
          >
            <ChevronLeft className="h-4 w-4" />
            Facility Dashboard
          </Link>
        </div>

        {/* Toolbar */}
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="h-4 w-4 text-emerald-500" aria-hidden="true" />
            <span className="text-sm text-muted-foreground">
              Showing{" "}
              <span className="font-medium text-foreground">
                {events.total.toLocaleString()}
              </span>{" "}
              approved upcoming{" "}
              {events.total === 1 ? "booking" : "bookings"}
              {selectedFacility !== "all" &&
                facilities.find((f) => String(f.id) === selectedFacility)?.name && (
                  <>
                    {" "}for{" "}
                    <span className="font-medium text-foreground">
                      {facilities.find((f) => String(f.id) === selectedFacility)?.name}
                    </span>
                  </>
                )}
            </span>
          </div>

          <Select value={selectedFacility} onValueChange={handleFacilityChange}>
            <SelectTrigger className="h-8 w-48 text-xs">
              <SelectValue placeholder="All Facilities" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Facilities</SelectItem>
              {facilities.map((f) => (
                <SelectItem key={f.id} value={String(f.id)}>
                  {f.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Event list */}
        {eventList.length === 0 ? (
          <div className="rounded-lg border border-border/60 bg-card py-20 text-center">
            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl border border-border/60 bg-muted/40">
              <Calendar className="h-5 w-5 text-muted-foreground" />
            </div>
            <p className="text-sm font-semibold text-foreground">
              No upcoming approved bookings
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
              {selectedFacility !== "all"
                ? "Try selecting a different facility"
                : "Approved bookings will appear here"}
            </p>
          </div>
        ) : (
          <div className="space-y-6">
            {grouped.map(({ date, items }) => (
              <div key={date}>
                {/* Date heading */}
                <div className="mb-2 flex items-center gap-3">
                  <div className="flex items-center gap-2">
                    <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
                    <h2 className="text-sm font-semibold text-foreground">
                      {formatDateHeading(date)}
                    </h2>
                  </div>
                  <span className="text-xs text-muted-foreground">
                    {items.length} {items.length === 1 ? "booking" : "bookings"}
                  </span>
                </div>

                {/* Events for this date */}
                <div className="overflow-hidden rounded-lg border border-border/60 bg-card">
                  {/* Column header */}
                  <div className="hidden sm:grid sm:grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1.5fr)] gap-4 border-b border-border/60 bg-muted/40 px-5 py-2.5">
                    <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Booking
                    </span>
                    <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Facility
                    </span>
                    <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Time
                    </span>
                    <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Requester
                    </span>
                  </div>

                  <div className="divide-y divide-border/40">
                    {items.map((event) => (
                      <div
                        key={event.id}
                        className="grid grid-cols-1 gap-2 px-5 py-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1.5fr)] sm:items-center hover:bg-accent/30 transition-colors"
                      >
                        {/* Booking title + type */}
                        <div className="min-w-0">
                          <p className="truncate text-sm font-medium text-foreground">
                            {event.title}
                          </p>
                          <div className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <FileText className="h-3 w-3 shrink-0" />
                            <span className="truncate">
                              {event.formType} #{event.submissionId}
                            </span>
                          </div>
                          {/* Mobile: date display */}
                          <p className="mt-0.5 text-xs text-muted-foreground sm:hidden tabular-nums">
                            {formatDateShort(event.date)}
                          </p>
                        </div>

                        {/* Facility */}
                        <div className="flex items-center gap-1.5 text-sm text-muted-foreground">
                          <MapPin className="h-3.5 w-3.5 shrink-0" />
                          <span className="truncate">{event.facilityName}</span>
                        </div>

                        {/* Time */}
                        <div className="flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums">
                          <Clock className="h-3.5 w-3.5 shrink-0" />
                          <span>
                            {event.startTime && event.endTime
                              ? `${formatTime(event.startTime)} \u2013 ${formatTime(event.endTime)}`
                              : event.startTime
                                ? formatTime(event.startTime)
                                : "All day"}
                          </span>
                        </div>

                        {/* Requester */}
                        <div className="flex items-center gap-1.5 text-sm text-muted-foreground">
                          <User className="h-3.5 w-3.5 shrink-0" />
                          <span className="truncate">{event.requester}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Pagination */}
        {events.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-border/60 pt-4">
            <p className="text-xs text-muted-foreground tabular-nums">
              Page {events.current_page} of {events.last_page}
              {events.from && events.to && (
                <> &mdash; showing {events.from}&ndash;{events.to} of {events.total.toLocaleString()}</>
              )}
            </p>
            <div className="flex items-center gap-1">
              <Button
                variant="outline"
                size="sm"
                className="h-8 w-8 p-0 touch-manipulation"
                disabled={events.current_page <= 1}
                onClick={() => goToPage(events.current_page - 1)}
                aria-label="Previous page"
              >
                <ChevronLeft className="h-4 w-4" />
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="h-8 w-8 p-0 touch-manipulation"
                disabled={events.current_page >= events.last_page}
                onClick={() => goToPage(events.current_page + 1)}
                aria-label="Next page"
              >
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        )}
      </div>
    </>
  )
}

FacilityUpcomingEventsPage.displayName = "FacilityUpcomingEventsPage"

export default FacilityUpcomingEventsPage

;(
  FacilityUpcomingEventsPage as React.FC<Props> & {
    layout?: (page: React.ReactNode) => React.ReactNode
  }
).layout = (page: React.ReactNode) => (
  <AppLayout title="Upcoming Events" subtitle="All upcoming approved facility bookings">
    {page}
  </AppLayout>
)
