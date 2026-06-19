import React from "react";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { ReportFiltersState, SubmissionStatus } from "../types";

interface FilterBarProps {
  filters: ReportFiltersState;
  onChange: (patch: Partial<ReportFiltersState>) => void;
}

const ALL_STATUSES_SENTINEL = "__all__";

const STATUSES: { value: SubmissionStatus | typeof ALL_STATUSES_SENTINEL; label: string }[] = [
  { value: ALL_STATUSES_SENTINEL, label: "All statuses" },
  { value: "pending", label: "Pending" },
  { value: "approved", label: "Approved" },
  { value: "rejected", label: "Rejected" },
  { value: "completed", label: "Completed" },
];

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

export const FilterBar: React.FC<FilterBarProps> = ({ filters, onChange }) => (
  <div className="flex flex-wrap items-end gap-3">
    {/* Date range */}
    <div className="flex flex-col gap-1">
      <label className="text-xs text-muted-foreground">From</label>
      <Input
        type="date"
        className="w-36 h-8 text-sm"
        value={filters.date_from ?? ""}
        onChange={(e) => onChange({ date_from: e.target.value || null })}
      />
    </div>
    <div className="flex flex-col gap-1">
      <label className="text-xs text-muted-foreground">To</label>
      <Input
        type="date"
        className="w-36 h-8 text-sm"
        value={filters.date_to ?? ""}
        onChange={(e) => onChange({ date_to: e.target.value || null })}
      />
    </div>

    {/* Status */}
    <div className="flex flex-col gap-1">
      <label className="text-xs text-muted-foreground">Status</label>
      <Select
        value={filters.submission_status || ALL_STATUSES_SENTINEL}
        onValueChange={(v) => onChange({ submission_status: (v === ALL_STATUSES_SENTINEL ? "" : v) as SubmissionStatus })}
      >
        <SelectTrigger className="w-40 h-8 text-sm">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {STATUSES.map((s) => (
            <SelectItem key={s.value} value={s.value}>
              {s.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>

    {/* Submitter */}
    <div className="flex flex-col gap-1">
      <label className="text-xs text-muted-foreground">Submitter</label>
      <Input
        className="w-44 h-8 text-sm"
        placeholder="Search by name or email"
        value={filters.submitter ?? ""}
        onChange={(e) => onChange({ submitter: e.target.value || null })}
      />
    </div>

    {/* Per page */}
    <div className="flex flex-col gap-1">
      <label className="text-xs text-muted-foreground">Per page</label>
      <Select
        value={String(filters.per_page)}
        onValueChange={(v) => onChange({ per_page: Number(v), page: 1 })}
      >
        <SelectTrigger className="w-20 h-8 text-sm">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {PER_PAGE_OPTIONS.map((n) => (
            <SelectItem key={n} value={String(n)}>
              {n}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  </div>
);
