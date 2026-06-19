import React from "react";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";

type StatusValue = "all" | "pending" | "approved" | "rejected";

export type SearchFilterState = {
  q: string;
  status: StatusValue;
};

type Props = {
  /** Controlled value coming from the parent */
  value: SearchFilterState;
  /** Called whenever q or status changes */
  onChange: (next: SearchFilterState) => void;
  /** Optional: clear filters callback (falls back to emitting {q:"",status:"all"}) */
  onClear?: () => void;
  /** Optional extra classes */
  className?: string;
  /** Debounce time in ms for search input */
  debounceMs?: number;
  /** Show the Clear button (default: true) */
  showClear?: boolean;
  /** Layout: "stacked" (default) or "twoCol" to place Search & Filter in 2 columns */
  layout?: "stacked" | "twoCol";
};

export default function SearchWithFilters({
  value,
  onChange,
  onClear,
  className = "",
  debounceMs = 300,
  showClear = true,
  layout = "stacked",
}: Props) {
  const searchId = React.useId();
  const statusId = React.useId();
  const [localQ, setLocalQ] = React.useState<string>(value.q);

  // keep local input in sync if parent resets externally
  React.useEffect(() => {
    setLocalQ(value.q);
  }, [value.q]);

  // debounce search updates
  React.useEffect(() => {
    const t = setTimeout(() => {
      if (localQ !== value.q) onChange({ ...value, q: localQ });
    }, debounceMs);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [localQ, debounceMs]);

  const handleStatusChange = (next: StatusValue) => {
    if (next !== value.status) onChange({ ...value, status: next });
  };

  const handleClear = () => {
    if (onClear) return onClear();
    onChange({ q: "", status: "all" });
  };

  if (layout === "twoCol") {
    // Compact two-column layout: Search | Filter (no right-side section)
    return (
      <div
        className={`grid grid-cols-2 gap-3 ${className}`}
        role="region"
        aria-label="Search and filter requests"
      >
        <div>
          <label htmlFor={searchId} className="sr-only">Search requests</label>
          <Input
            id={searchId}
            type="text"
            placeholder="Search …"
            className="w-full"
            value={localQ}
            onChange={(e) => setLocalQ(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Escape") handleClear();
            }}
          />
        </div>
        <div>
          <label id={statusId} className="sr-only">Filter by status</label>
          <Select
            value={value.status}
            onValueChange={(v) => handleStatusChange(v as StatusValue)}
          >
            <SelectTrigger className="w-full" aria-labelledby={statusId}>
              <SelectValue placeholder="All Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Status</SelectItem>
              <SelectItem value="pending">Pending</SelectItem>
              <SelectItem value="approved">Approved</SelectItem>
              <SelectItem value="rejected">Rejected</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Hidden clear in two-col when showClear=false; render as a small row if needed */}
        {showClear && (
          <div className="col-span-2 flex">
            <Button variant="outline" onClick={handleClear} className="ml-auto">
              Clear
            </Button>
          </div>
        )}
      </div>
    );
  }

  // Default stacked/flex layout (original)
  return (
    <div
      className={`flex flex-col md:flex-row md:items-center md:justify-between gap-3 ${className}`}
      role="region"
      aria-label="Search and filter requests"
    >
      {/* Left: Search + Status */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full">
        <div className="relative flex-1 md:max-w-md">
          <svg 
            className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none"
            fill="none" 
            viewBox="0 0 24 24" 
            stroke="currentColor"
            aria-hidden="true"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <label htmlFor={searchId} className="sr-only">Search requests</label>
          <Input
            id={searchId}
            type="text"
            placeholder="Search by name, code, or status…"
            className="
              w-full pl-10 pr-4 h-10
              border border-border/60
              rounded-lg
              transition-all duration-200
            "
            value={localQ}
            onChange={(e) => setLocalQ(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Escape") handleClear();
            }}
          />
        </div>

        <div className="relative w-full sm:w-auto sm:min-w-[160px]">
          <label id={statusId} className="sr-only">Filter by status</label>
          <Select
            value={value.status}
            onValueChange={(v) => handleStatusChange(v as StatusValue)}
          >
            <SelectTrigger 
              className="
                h-10 w-full
                border border-border/60
                rounded-lg
                transition-all duration-200
              " 
              aria-labelledby={statusId}
            >
              <div className="flex items-center gap-2">
                <svg className="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <SelectValue placeholder="All Status" />
              </div>
            </SelectTrigger>
            <SelectContent className="rounded-lg">
              <SelectItem value="all" className="cursor-pointer">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-gray-400" aria-hidden="true" />
                  <span>All Status</span>
                </div>
              </SelectItem>
              <SelectItem value="pending" className="cursor-pointer">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-amber-500" aria-hidden="true" />
                  <span>Pending</span>
                </div>
              </SelectItem>
              <SelectItem value="approved" className="cursor-pointer">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-emerald-500" aria-hidden="true" />
                  <span>Approved</span>
                </div>
              </SelectItem>
              <SelectItem value="rejected" className="cursor-pointer">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-rose-500" aria-hidden="true" />
                  <span>Rejected</span>
                </div>
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Right: Clear */}
      {showClear && (
        <div className="flex justify-start md:justify-end">
          <Button 
            variant="outline" 
            onClick={handleClear}
            className="
              h-10 px-4
              border border-border
              hover:bg-muted
              transition-all duration-200
              text-sm
            "
          >
            <svg className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
            Clear Filters
          </Button>
        </div>
      )}
    </div>
  );
}
