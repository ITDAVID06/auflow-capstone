import React from "react";
import { format, addMonths } from "date-fns";
import { CalendarIcon, Plus, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Label } from "@/components/ui/label";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs";

import type { SelectedSlot } from "@/types/form";

interface Facility {
  id: number;
  name: string;
}

interface DateTimeSectionProps {
  title: string;
  requireFacility: boolean;
  facilities: Facility[];
  dtTempDate: Date | undefined;
  dtTempStart: string;
  dtTempEnd: string;
  dtTempFacility: string;
  dtSlots: SelectedSlot[];
  timeSlots: string[];
  calendarDays: Date[];
  unavailableSlots: Array<{
    date: string;
    start_time?: string;
    end_time?: string;
    facility_id?: number | null;
  }>;
  currentDate: Date;
  canGoPrev: boolean;
  setDtTempDate: (date: Date | undefined) => void;
  setDtTempStart: (value: string) => void;
  setDtTempEnd: (value: string) => void;
  setDtTempFacility: (value: string) => void;
  addSlot: () => void;
  removeSlot: (index: number) => void;
  setCurrentDate: (date: Date) => void;
  isTimeDisabled: (time: string) => boolean;
}

export const DateTimeSection: React.FC<DateTimeSectionProps> = ({
  title,
  requireFacility,
  facilities,
  dtTempDate,
  dtTempStart,
  dtTempEnd,
  dtTempFacility,
  dtSlots,
  timeSlots,
  calendarDays,
  unavailableSlots,
  currentDate,
  canGoPrev,
  setDtTempDate,
  setDtTempStart,
  setDtTempEnd,
  setDtTempFacility,
  addSlot,
  removeSlot,
  setCurrentDate,
  isTimeDisabled,
}) => {
  return (
    <section className="space-y-4 pb-6 border-b border-gray-200 dark:border-gray-700">
      <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
      <div className="space-y-4">
        <Tabs defaultValue="form">
          <TabsList className="bg-transparent">
            <TabsTrigger value="form" className="rounded-full">
              Form View
            </TabsTrigger>
            <TabsTrigger value="calendar" className="rounded-full">
              Calendar View
            </TabsTrigger>
          </TabsList>

          <TabsContent value="form" className="space-y-4">
            <div className="grid items-end gap-3 md:grid-cols-8 lg:grid-cols-12">
              <div className="md:col-span-4 lg:col-span-5">
                <Label>Date</Label>
                <Popover>
                  <PopoverTrigger asChild>
                    <Button
                      variant="outline"
                      className="min-w-[220px] w-full justify-start text-left"
                    >
                      <CalendarIcon className="mr-2 h-4 w-4" />
                      {dtTempDate ? format(dtTempDate, "MMMM dd, yyyy") : "Select date"}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0">
                    <Calendar mode="single" selected={dtTempDate} onSelect={setDtTempDate} />
                  </PopoverContent>
                </Popover>
              </div>

              <div className="md:col-span-2 lg:col-span-2">
                <Label>Start Time</Label>
                <Select value={dtTempStart} onValueChange={setDtTempStart}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Start time" />
                  </SelectTrigger>
                  <SelectContent>
                    {timeSlots.map((slot) => (
                      <SelectItem key={slot} value={slot} disabled={isTimeDisabled(slot)}>
                        {slot}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="md:col-span-2 lg:col-span-2">
                <Label>End Time</Label>
                <Select value={dtTempEnd} onValueChange={setDtTempEnd}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="End time" />
                  </SelectTrigger>
                  <SelectContent>
                    {timeSlots
                      .filter((slot) => !dtTempStart || slot > dtTempStart)
                      .map((slot) => (
                        <SelectItem key={slot} value={slot} disabled={isTimeDisabled(slot)}>
                          {slot}
                        </SelectItem>
                      ))}
                  </SelectContent>
                </Select>
              </div>

              {requireFacility && (
                <div className="md:col-span-3 lg:col-span-2">
                  <Label>Facility</Label>
                  <Select value={dtTempFacility} onValueChange={setDtTempFacility}>
                    <SelectTrigger className="w-full">
                      <SelectValue placeholder="Select facility" />
                    </SelectTrigger>
                    <SelectContent>
                      {facilities.map((facility) => (
                        <SelectItem key={facility.id} value={String(facility.id)}>
                          {facility.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}

              <div className="md:col-span-1 lg:col-span-1">
                <Button
                  type="button"
                  onClick={addSlot}
                  disabled={
                    !dtTempDate || !dtTempStart || !dtTempEnd || (requireFacility && !dtTempFacility)
                  }
                  className="w-full"
                >
                  <Plus className="mr-2 h-4 w-4" /> Add
                </Button>
              </div>
            </div>

            {dtSlots.length > 0 && (
              <div className="space-y-2">
                {dtSlots.map((slot, index) => (
                  <div
                    key={index}
                    className="flex items-center justify-between rounded border border-gray-200 dark:border-gray-700 p-2"
                  >
                    <span className="text-sm">
                      {format(slot.date, "MMMM dd, yyyy")} | {slot.start_time} – {slot.end_time}
                      {requireFacility && slot.facility_id
                        ? ` | ${
                            facilities.find((facility) => String(facility.id) === slot.facility_id)
                              ?.name || `Facility ${slot.facility_id}`
                          }`
                        : ""}
                    </span>
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      onClick={() => removeSlot(index)}
                      aria-label={`Remove slot: ${format(slot.date, "MMMM dd, yyyy")} ${slot.start_time}–${slot.end_time}`}
                    >
                      <X className="h-4 w-4 text-red-600" aria-hidden="true" />
                    </Button>
                  </div>
                ))}
              </div>
            )}
          </TabsContent>

          <TabsContent value="calendar" className="space-y-4">
            <div className="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
              <div className="mb-3 flex items-center justify-between">
                <h3 className="font-semibold">{format(currentDate, "MMMM yyyy")}</h3>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setCurrentDate(addMonths(currentDate, -1))}
                    disabled={!canGoPrev}
                  >
                    Prev
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setCurrentDate(addMonths(currentDate, 1))}
                  >
                    Next
                  </Button>
                </div>
              </div>

              {/* Horizontal scroll wrapper keeps the 7-col grid usable on narrow screens */}
              <div className="overflow-x-auto -mx-4 px-4">
                <div className="min-w-[420px]">
                  {/* Day-of-week headers */}
                  <div className="grid grid-cols-7 gap-1 mb-1">
                    {["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].map((d) => (
                      <div key={d} className="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">
                        {d}
                      </div>
                    ))}
                  </div>

                  <div className="grid grid-cols-7 gap-1">
                    {calendarDays.map((day) => {
                      const dateKey = format(day, "yyyy-MM-dd");
                      const daySlots = unavailableSlots.filter((slot) => slot.date === dateKey);
                      const isAvailable = daySlots.length === 0;
                      return (
                        <div
                          key={dateKey}
                          className="min-h-[90px] rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1.5 text-sm"
                        >
                          <span className="font-medium text-xs">{format(day, "d")}</span>
                          {isAvailable ? (
                            <div className="mt-1 flex items-center gap-0.5">
                              <span className="inline-block h-2 w-2 rounded-full bg-emerald-500 flex-shrink-0" aria-hidden="true" />
                              <span className="text-[10px] text-emerald-700 dark:text-emerald-400 leading-tight">
                                Open
                              </span>
                            </div>
                          ) : (
                            <div className="mt-1 space-y-1">
                              {daySlots.map((slot, slotIndex) => (
                                <div
                                  key={slotIndex}
                                  className="rounded bg-red-100 dark:bg-red-900/40 px-1 py-0.5 text-[10px] text-red-800 dark:text-red-300 flex items-start gap-0.5"
                                >
                                  <span className="inline-block mt-0.5 h-1.5 w-1.5 rounded-full bg-red-500 flex-shrink-0" aria-hidden="true" />
                                  <span className="leading-tight">
                                    {slot.start_time && slot.end_time
                                      ? `Booked ${slot.start_time}–${slot.end_time}`
                                      : "Unavailable"}
                                    {slot.facility_id &&
                                      ` · ${
                                        facilities.find((facility) => facility.id === slot.facility_id)?.name ||
                                        `Facility ${slot.facility_id}`
                                      }`}
                                  </span>
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                </div>
              </div>
            </div>
          </TabsContent>
        </Tabs>
      </div>
    </section>
  );
};
