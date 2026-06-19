import React from "react";
import { format } from "date-fns";
import { CalendarIcon, Plus, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Label } from "@/components/ui/label";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";

interface DateRangeSectionProps {
  title: string;
  rangeStart: Date | undefined;
  rangeEnd: Date | undefined;
  setRangeStart: (date: Date | undefined) => void;
  setRangeEnd: (date: Date | undefined) => void;
  addDateRange: () => void;
  removeDateRange: (index: number) => void;
  dateRanges: Array<{ from: Date; to: Date }>;
  disabled?: boolean;
}

export const DateRangeSection: React.FC<DateRangeSectionProps> = ({
  title,
  rangeStart,
  rangeEnd,
  setRangeStart,
  setRangeEnd,
  addDateRange,
  removeDateRange,
  dateRanges,
}) => {
  return (
    <section className="space-y-4 pb-6 border-b border-gray-200 dark:border-gray-700">
      <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
      <div className="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <Label>From</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" className="w-full justify-start text-left">
                <CalendarIcon className="mr-2 h-4 w-4" />
                {rangeStart ? format(rangeStart, "MMMM dd, yyyy") : "Select start date"}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0">
              <Calendar mode="single" selected={rangeStart} onSelect={setRangeStart} />
            </PopoverContent>
          </Popover>
        </div>

        <div>
          <Label>To</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" className="w-full justify-start text-left">
                <CalendarIcon className="mr-2 h-4 w-4" />
                {rangeEnd ? format(rangeEnd, "MMMM dd, yyyy") : "Select end date"}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0">
              <Calendar mode="single" selected={rangeEnd} onSelect={setRangeEnd} />
            </PopoverContent>
          </Popover>
        </div>

        <div className="sm:col-span-2 lg:col-span-1">
          <Button
            type="button"
            className="w-full bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-700"
            disabled={!rangeStart || !rangeEnd || rangeEnd < rangeStart}
            onClick={addDateRange}
          >
            <Plus className="mr-2 h-4 w-4" /> Add Range
          </Button>
        </div>
      </div>

      {dateRanges.length > 0 && (
        <div className="space-y-2">
          {dateRanges.map((range, index) => (
            <div
              key={index}
              className="flex items-center justify-between rounded border border-gray-200 dark:border-gray-700 p-2"
            >
              <span className="text-gray-900 dark:text-gray-100">
                {format(range.from, "MMMM dd, yyyy")} – {format(range.to, "MMMM dd, yyyy")}
              </span>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => removeDateRange(index)}
                aria-label={`Remove date range: ${format(range.from, "MMMM dd, yyyy")} to ${format(range.to, "MMMM dd, yyyy")}`}
              >
                <X className="h-4 w-4 text-red-600" aria-hidden="true" />
              </Button>
            </div>
          ))}
        </div>
      )}
    </section>
  );
};
