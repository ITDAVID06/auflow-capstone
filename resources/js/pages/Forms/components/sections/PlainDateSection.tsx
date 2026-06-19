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

interface PlainDateSectionProps {
  title: string;
  plainTempDate: Date | undefined;
  setPlainTempDate: (date: Date | undefined) => void;
  unavailableDates: string[];
  plainDates: Date[];
  addPlainDate: () => void;
  removePlainDate: (index: number) => void;
}

export const PlainDateSection: React.FC<PlainDateSectionProps> = ({
  title,
  plainTempDate,
  setPlainTempDate,
  unavailableDates,
  plainDates,
  addPlainDate,
  removePlainDate,
}) => {
  return (
    <section className="space-y-4 pb-6 border-b border-gray-200 dark:border-gray-700">
      <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
      <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-end">
        <div className="sm:w-[260px]">
          <Label>Date</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" className="w-full justify-start text-left">
                <CalendarIcon className="mr-2 h-4 w-4" />
                {plainTempDate ? format(plainTempDate, "MMMM dd, yyyy") : "Select date"}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0">
              <Calendar
                mode="single"
                selected={plainTempDate}
                onSelect={setPlainTempDate}
                disabled={(date) =>
                  unavailableDates.includes(format(date, "yyyy-MM-dd"))
                }
              />
            </PopoverContent>
          </Popover>
        </div>

        <Button
          type="button"
          onClick={addPlainDate}
          disabled={!plainTempDate}
          className="bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-700"
        >
          <Plus className="mr-2 h-4 w-4" /> Add Date
        </Button>
      </div>

      {plainDates.length > 0 && (
        <div className="space-y-2">
          {plainDates.map((date, index) => (
            <div
              key={index}
              className="flex items-center justify-between rounded border border-gray-200 dark:border-gray-700 p-2"
            >
              <span className="text-gray-900 dark:text-gray-100">{format(date, "MMMM dd, yyyy")}</span>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => removePlainDate(index)}
                aria-label={`Remove date: ${format(date, "MMMM dd, yyyy")}`}
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
