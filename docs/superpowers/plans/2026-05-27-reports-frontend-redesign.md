# Reports Frontend Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the monolithic `ReportsPage.tsx` with a tab-organised shell (Overview / Data / Exports / Compare) where each tab fetches its own data independently.

**Architecture:** `ReportsController::index()` becomes a thin shell renderer (no data assembly). The frontend reads `form_id` and `tab` from URL query params and dispatches per-tab axios calls to existing JSON endpoints. New tab components live under `resources/js/pages/Reports/tabs/`; shared components are reorganised under `resources/js/pages/Reports/components/`. Old components are deleted at the end.

**Tech Stack:** React 18, TypeScript, Inertia.js, axios, shadcn/ui (Tabs, Card, Button, etc.), Recharts (already used in existing charts), Tailwind CSS

---

## Prerequisites

- Backend fixes plan (`2026-05-27-reports-backend-fixes.md`) should be merged first, or developed on a parallel branch. This plan does not depend on it at the code level, but both touch the same controller.

---

## File Map

**Simplify (modify):**
- `app/Modules/Reports/Controllers/ReportsController.php` — strip data assembly from `index()`
- `resources/js/pages/Reports/types.ts` — add tab-specific types
- `resources/js/pages/Reports/hooks/useReportFilters.ts` — add `exportToFilterState()` helper
- `resources/js/pages/Reports/hooks/useAsyncExport.ts` — no changes needed
- `resources/js/pages/Reports/hooks/useChartData.ts` — no changes needed

**Create:**
- `resources/js/pages/Reports/tabs/OverviewTab.tsx`
- `resources/js/pages/Reports/tabs/DataTab.tsx`
- `resources/js/pages/Reports/tabs/ExportsTab.tsx`
- `resources/js/pages/Reports/tabs/CompareTab.tsx`
- `resources/js/pages/Reports/components/KpiCard.tsx`
- `resources/js/pages/Reports/components/FormPicker.tsx`
- `resources/js/pages/Reports/components/FilterBar.tsx`
- `resources/js/pages/Reports/components/AdvancedFilters.tsx`
- `resources/js/pages/Reports/components/SavedViewsPills.tsx`
- `resources/js/pages/Reports/components/SubmissionTable.tsx`
- `resources/js/pages/Reports/components/ColumnSelector.tsx`
- `resources/js/pages/Reports/components/ExportButton.tsx`
- `resources/js/pages/Reports/components/ScheduledExportModal.tsx`

**Rewrite:**
- `resources/js/pages/Reports/ReportsPage.tsx` — shell only (form picker + tabs)

**Delete (after all new files are working):**
- `resources/js/pages/Reports/components/ReportHero.tsx`
- `resources/js/pages/Reports/components/ReportFilters.tsx`
- `resources/js/pages/Reports/components/ReportTable.tsx`
- `resources/js/pages/Reports/components/ReportCharts.tsx`
- `resources/js/pages/Reports/components/CompareFormsModal.tsx`
- `resources/js/pages/Reports/components/ScheduledExportsModal.tsx`
- `resources/js/pages/Reports/components/AggregationSection.tsx`
- `resources/js/pages/Reports/components/BuilderFilterList.tsx`
- `resources/js/pages/Reports/components/ColumnPicker.tsx`
- `resources/js/pages/Reports/components/ExportOptionsBar.tsx`
- `resources/js/pages/Reports/components/FormFieldDistributionChart.tsx`
- `resources/js/pages/Reports/components/ReportFormPicker.tsx`
- `resources/js/pages/Reports/components/SavedReportsDropdown.tsx`
- `resources/js/pages/Reports/components/SortSection.tsx`
- `resources/js/pages/Reports/components/StatusBreakdownChart.tsx`
- `resources/js/pages/Reports/components/SubmissionTrendChart.tsx`
- `resources/js/pages/Reports/components/DatePickerInput.tsx`
- `resources/js/pages/Reports/components/DateRangeSection.tsx`

---

## Task 1: Simplify `ReportsController::index()` + update `types.ts`

This is an atomic backend+frontend change. Do both steps before committing.

**Files:**
- Modify: `app/Modules/Reports/Controllers/ReportsController.php`
- Modify: `resources/js/pages/Reports/types.ts`

- [ ] **Step 1.1: Strip data assembly from `index()`**

Replace the `index()` method in `app/Modules/Reports/Controllers/ReportsController.php`:

```php
/**
 * Display the reports page shell.
 * Each tab fetches its own data via client-side JSON calls.
 *
 * @return \Inertia\Response
 */
public function index()
{
    return Inertia::render('Reports/ReportsPage', [
        'error' => session('error'),
    ]);
}
```

Also remove the `ReportsFilterRequest` import from the `index` method signature (it no longer validates on page load). The import line `use App\Modules\Reports\Requests\ReportsFilterRequest;` can remain — it is still used by other methods.

- [ ] **Step 1.2: Update `types.ts` — add tab and new types**

Append the following to the end of `resources/js/pages/Reports/types.ts`:

```typescript
// ─── Tab architecture ────────────────────────────────────────────────────────

export type ReportTab = "overview" | "data" | "exports" | "compare";

export interface KpiData {
  total_submissions: number;
  approved: number;
  pending: number;
  avg_completion_human: string | null;
}

export interface TrendPoint {
  date: string;
  count: number;
}

export interface StatusBreakdownPoint {
  status: string;
  count: number;
}

export interface FieldDistributionPoint {
  value: string;
  count: number;
}

export interface ChartDataResponse {
  kpi: KpiData;
  trend: TrendPoint[];
  status_breakdown: StatusBreakdownPoint[];
  field_distribution: FieldDistributionPoint[];
  field_distribution_column: string | null;
  available_field_columns: { key: string; label: string }[];
}

export interface ScheduledExport {
  id: number;
  form_id: number;
  form: { id: number; form_name: string; form_code: string } | null;
  recipient_email: string;
  frequency: "daily" | "weekly" | "monthly";
  export_type: "csv" | "pdf";
  filter_state: Record<string, unknown> | null;
  is_active: boolean;
  last_sent_at: string | null;
  created_by: number;
}

export interface ReportView {
  id: number;
  form_id: number;
  name: string;
  filter_state: ReportFiltersState;
  created_by: number;
}

// FilterState snapshot suitable for storing in a ScheduledExport.filter_state
export type FilterStateSnapshot = Omit<ReportFiltersState, "form_id" | "page" | "per_page"> & {
  filters: ReportFilterItem[];
};
```

- [ ] **Step 1.3: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

Expected: No errors.

- [ ] **Step 1.4: Commit**

```bash
git add app/Modules/Reports/Controllers/ReportsController.php \
        resources/js/pages/Reports/types.ts
git commit -m "refactor: strip data assembly from ReportsController::index(); add tab types"
```

---

## Task 2: Shared components — `KpiCard` and `FormPicker`

**Files:**
- Create: `resources/js/pages/Reports/components/KpiCard.tsx`
- Create: `resources/js/pages/Reports/components/FormPicker.tsx`

- [ ] **Step 2.1: Create `KpiCard.tsx`**

```tsx
import React from "react";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";

interface KpiCardProps {
  label: string;
  value: string | number | null;
  loading?: boolean;
  className?: string;
}

export const KpiCard: React.FC<KpiCardProps> = ({ label, value, loading = false, className }) => (
  <Card className={cn("flex-1 min-w-[140px]", className)}>
    <CardContent className="pt-4 pb-3">
      <p className="text-xs text-muted-foreground uppercase tracking-wide mb-1">{label}</p>
      {loading ? (
        <div className="h-7 w-16 rounded bg-muted animate-pulse" />
      ) : (
        <p className="text-2xl font-semibold tabular-nums">{value ?? "—"}</p>
      )}
    </CardContent>
  </Card>
);
```

- [ ] **Step 2.2: Create `FormPicker.tsx`**

This component renders the top-level form selector. It fetches the list of forms from `GET /reports/forms` and calls `onChange` with the selected form id. If `selectedFormId` is provided it pre-selects that form.

```tsx
import React, { useEffect, useState } from "react";
import axios from "axios";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { ReportForm } from "../types";

interface FormPickerProps {
  selectedFormId: number | null;
  onChange: (formId: number | null) => void;
}

export const FormPicker: React.FC<FormPickerProps> = ({ selectedFormId, onChange }) => {
  const [forms, setForms] = useState<ReportForm[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    axios
      .get<ReportForm[]>(route("reports.forms"))
      .then((r) => setForms(r.data))
      .catch(() => setForms([]))
      .finally(() => setLoading(false));
  }, []);

  return (
    <Select
      value={selectedFormId ? String(selectedFormId) : ""}
      onValueChange={(v) => onChange(v ? Number(v) : null)}
      disabled={loading}
    >
      <SelectTrigger className="w-72">
        <SelectValue placeholder={loading ? "Loading forms…" : "Select a form"} />
      </SelectTrigger>
      <SelectContent>
        {forms.map((f) => (
          <SelectItem key={f.id} value={String(f.id)}>
            {f.form_name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
};
```

- [ ] **Step 2.3: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

- [ ] **Step 2.4: Commit**

```bash
git add resources/js/pages/Reports/components/KpiCard.tsx \
        resources/js/pages/Reports/components/FormPicker.tsx
git commit -m "feat(reports): add KpiCard and FormPicker shared components"
```

---

## Task 3: Shared components — `FilterBar` and `AdvancedFilters`

**Files:**
- Create: `resources/js/pages/Reports/components/FilterBar.tsx`
- Create: `resources/js/pages/Reports/components/AdvancedFilters.tsx`

- [ ] **Step 3.1: Create `FilterBar.tsx`**

Renders the always-visible top filter controls: date range, status dropdown, submitter search, per-page selector. Calls `onChange` on any change.

```tsx
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

const STATUSES: { value: SubmissionStatus; label: string }[] = [
  { value: "", label: "All statuses" },
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
        value={filters.submission_status}
        onValueChange={(v) => onChange({ submission_status: v as SubmissionStatus })}
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
```

- [ ] **Step 3.2: Create `AdvancedFilters.tsx`**

Wraps the existing `BuilderFilterList` component (kept from old codebase) in a collapsible toggle. If `BuilderFilterList` is being deleted as part of this plan, inline its key logic here. For now, this file acts as the collapsible shell; the internals reuse the existing builder UI.

```tsx
import React, { useState } from "react";
import { Button } from "@/components/ui/button";
import { ChevronDown, ChevronRight } from "lucide-react";
import { ReportBuilderCapabilities, ReportFilterItem } from "../types";
import BuilderFilterList from "./BuilderFilterList";

interface AdvancedFiltersProps {
  filters: ReportFilterItem[];
  capabilities: ReportBuilderCapabilities;
  onChange: (filters: ReportFilterItem[]) => void;
}

export const AdvancedFilters: React.FC<AdvancedFiltersProps> = ({
  filters,
  capabilities,
  onChange,
}) => {
  const [open, setOpen] = useState(false);
  const hasActive = filters.length > 0;

  return (
    <div>
      <Button
        variant="ghost"
        size="sm"
        className="gap-1 text-muted-foreground hover:text-foreground"
        onClick={() => setOpen((v) => !v)}
      >
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        Advanced filters
        {hasActive && (
          <span className="ml-1 rounded-full bg-primary text-primary-foreground text-[10px] px-1.5 py-0.5">
            {filters.length}
          </span>
        )}
      </Button>

      {open && (
        <div className="mt-3 border rounded-md p-4 bg-muted/30">
          <BuilderFilterList
            filters={filters}
            capabilities={capabilities}
            onChange={onChange}
          />
        </div>
      )}
    </div>
  );
};
```

- [ ] **Step 3.3: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

- [ ] **Step 3.4: Commit**

```bash
git add resources/js/pages/Reports/components/FilterBar.tsx \
        resources/js/pages/Reports/components/AdvancedFilters.tsx
git commit -m "feat(reports): add FilterBar and AdvancedFilters shared components"
```

---

## Task 4: Shared components — `SavedViewsPills`, `ColumnSelector`

**Files:**
- Create: `resources/js/pages/Reports/components/SavedViewsPills.tsx`
- Create: `resources/js/pages/Reports/components/ColumnSelector.tsx`

- [ ] **Step 4.1: Create `SavedViewsPills.tsx`**

```tsx
import React, { useState } from "react";
import axios from "axios";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Trash2 } from "lucide-react";
import { toast } from "sonner";
import { ReportFiltersState, ReportView } from "../types";

interface SavedViewsPillsProps {
  views: ReportView[];
  currentFilters: ReportFiltersState;
  onLoad: (filters: ReportFiltersState) => void;
  onViewsChange: (views: ReportView[]) => void;
}

export const SavedViewsPills: React.FC<SavedViewsPillsProps> = ({
  views,
  currentFilters,
  onLoad,
  onViewsChange,
}) => {
  const [saveOpen, setSaveOpen] = useState(false);
  const [name, setName] = useState("");
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    if (!name.trim()) return;
    setSaving(true);
    try {
      const { data } = await axios.post<ReportView>(route("reports.views.store"), {
        form_id: currentFilters.form_id,
        name: name.trim(),
        filter_state: currentFilters,
      });
      onViewsChange([...views, data]);
      setSaveOpen(false);
      setName("");
      toast.success("View saved.");
    } catch {
      toast.error("Could not save view.");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (view: ReportView) => {
    try {
      await axios.delete(route("reports.views.destroy", view.id));
      onViewsChange(views.filter((v) => v.id !== view.id));
      toast.success("View deleted.");
    } catch {
      toast.error("Could not delete view.");
    }
  };

  return (
    <>
      <div className="flex flex-wrap items-center gap-2">
        {views.map((view) => (
          <Badge
            key={view.id}
            variant="secondary"
            className="cursor-pointer flex items-center gap-1 pr-1"
          >
            <span onClick={() => onLoad(view.filter_state)} className="px-1">
              {view.name}
            </span>
            <button
              onClick={() => handleDelete(view)}
              className="hover:text-destructive ml-0.5"
              aria-label={`Delete view "${view.name}"`}
            >
              <Trash2 className="h-3 w-3" />
            </button>
          </Badge>
        ))}
        <Button variant="outline" size="sm" onClick={() => setSaveOpen(true)}>
          Save current view
        </Button>
      </div>

      <Dialog open={saveOpen} onOpenChange={setSaveOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>Save view</DialogTitle>
          </DialogHeader>
          <Input
            placeholder="View name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            onKeyDown={(e) => e.key === "Enter" && handleSave()}
            autoFocus
          />
          <DialogFooter>
            <Button variant="outline" onClick={() => setSaveOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={saving || !name.trim()}>
              Save
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
};
```

- [ ] **Step 4.2: Create `ColumnSelector.tsx`**

Multi-select popover for toggling visible columns.

```tsx
import React from "react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Columns } from "lucide-react";
import { ReportColumn } from "../types";

interface ColumnSelectorProps {
  available: ReportColumn[];
  selected: string[];
  onChange: (selected: string[]) => void;
}

export const ColumnSelector: React.FC<ColumnSelectorProps> = ({
  available,
  selected,
  onChange,
}) => {
  const toggle = (key: string) => {
    const next = selected.includes(key)
      ? selected.filter((k) => k !== key)
      : [...selected, key];
    onChange(next);
  };

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" size="sm" className="gap-1">
          <Columns className="h-4 w-4" />
          Columns
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-52 p-2 max-h-72 overflow-y-auto">
        {available.map((col) => (
          <label
            key={col.key}
            className="flex items-center gap-2 px-2 py-1 rounded hover:bg-muted cursor-pointer text-sm"
          >
            <Checkbox
              checked={selected.includes(col.key)}
              onCheckedChange={() => toggle(col.key)}
            />
            {col.label}
          </label>
        ))}
      </PopoverContent>
    </Popover>
  );
};
```

- [ ] **Step 4.3: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

- [ ] **Step 4.4: Commit**

```bash
git add resources/js/pages/Reports/components/SavedViewsPills.tsx \
        resources/js/pages/Reports/components/ColumnSelector.tsx
git commit -m "feat(reports): add SavedViewsPills and ColumnSelector shared components"
```

---

## Task 5: Shared components — `SubmissionTable`, `ExportButton`, `ScheduledExportModal`

**Files:**
- Create: `resources/js/pages/Reports/components/SubmissionTable.tsx`
- Create: `resources/js/pages/Reports/components/ExportButton.tsx`
- Create: `resources/js/pages/Reports/components/ScheduledExportModal.tsx`

- [ ] **Step 5.1: Create `SubmissionTable.tsx`**

Renders the paginated submission rows with an expandable attachment list. The expand chevron toggles attachment visibility per row.

```tsx
import React, { useState } from "react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ChevronDown, ChevronRight, Download, Eye, FileIcon, ImageIcon } from "lucide-react";
import { Attachment, ReportColumn, ReportPagination, ReportSubmission } from "../types";

interface SubmissionTableProps {
  columns: ReportColumn[];
  submissions: ReportSubmission[];
  pagination: ReportPagination;
  onPageChange: (page: number) => void;
  loading?: boolean;
}

const AttachmentRow: React.FC<{ attachments: Attachment[] }> = ({ attachments }) => {
  if (attachments.length === 0) return null;
  return (
    <div className="flex flex-wrap gap-2 py-2 px-4 bg-muted/40 rounded">
      {attachments.map((a) => (
        <div
          key={a.id}
          className="flex items-center gap-2 text-sm border rounded px-2 py-1 bg-background"
        >
          {a.is_image ? (
            <ImageIcon className="h-4 w-4 text-muted-foreground" />
          ) : (
            <FileIcon className="h-4 w-4 text-muted-foreground" />
          )}
          <span className="max-w-[160px] truncate">{a.original_name}</span>
          <a
            href={route("reports.attachments.preview", a.id)}
            target="_blank"
            rel="noopener noreferrer"
            className="text-muted-foreground hover:text-foreground"
            aria-label={`Preview ${a.original_name}`}
          >
            <Eye className="h-4 w-4" />
          </a>
          <a
            href={route("reports.attachments.download", a.id)}
            className="text-muted-foreground hover:text-foreground"
            aria-label={`Download ${a.original_name}`}
          >
            <Download className="h-4 w-4" />
          </a>
        </div>
      ))}
    </div>
  );
};

export const SubmissionTable: React.FC<SubmissionTableProps> = ({
  columns,
  submissions,
  pagination,
  onPageChange,
  loading = false,
}) => {
  const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());

  const toggleExpand = (id: number) => {
    setExpandedIds((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };

  return (
    <div className="space-y-3">
      <div className="rounded-md border overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-8" />
              {columns.map((col) => (
                <TableHead key={col.key}>{col.label}</TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <TableRow key={i}>
                  <TableCell colSpan={columns.length + 1}>
                    <div className="h-4 w-full rounded bg-muted animate-pulse" />
                  </TableCell>
                </TableRow>
              ))
            ) : submissions.length === 0 ? (
              <TableRow>
                <TableCell colSpan={columns.length + 1} className="text-center py-8 text-muted-foreground">
                  No submissions found.
                </TableCell>
              </TableRow>
            ) : (
              submissions.map((row) => {
                const isExpanded = expandedIds.has(row.id);
                return (
                  <React.Fragment key={row.id}>
                    <TableRow className="group">
                      <TableCell className="pr-0">
                        {row.attachment_count > 0 && (
                          <button
                            onClick={() => toggleExpand(row.id)}
                            className="text-muted-foreground hover:text-foreground"
                            aria-label={isExpanded ? "Collapse attachments" : "Expand attachments"}
                          >
                            {isExpanded ? (
                              <ChevronDown className="h-4 w-4" />
                            ) : (
                              <ChevronRight className="h-4 w-4" />
                            )}
                          </button>
                        )}
                      </TableCell>
                      {columns.map((col) => (
                        <TableCell key={col.key}>
                          {col.key === "submission_status" ? (
                            <Badge variant="outline">
                              {String(row[col.key] ?? "—")}
                            </Badge>
                          ) : (
                            String(row[col.key] ?? "—")
                          )}
                        </TableCell>
                      ))}
                    </TableRow>
                    {isExpanded && row.attachments?.length > 0 && (
                      <TableRow>
                        <TableCell colSpan={columns.length + 1} className="p-0">
                          <AttachmentRow attachments={row.attachments} />
                        </TableCell>
                      </TableRow>
                    )}
                  </React.Fragment>
                );
              })
            )}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      {pagination.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">
            Page {pagination.current_page} of {pagination.last_page} — {pagination.total} total
          </span>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page <= 1}
              onClick={() => onPageChange(pagination.current_page - 1)}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() => onPageChange(pagination.current_page + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  );
};
```

- [ ] **Step 5.2: Create `ExportButton.tsx`**

Dropdown button for CSV / PDF export variants. Handles async threshold: when the server returns 202, calls `onAsyncExport` with the export id so the Exports tab can begin polling.

```tsx
import React, { useState } from "react";
import axios from "axios";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Download, ChevronDown } from "lucide-react";
import { toast } from "sonner";
import { buildReportQueryParams } from "../queryBuilder";
import { ReportFiltersState } from "../types";

type ExportLimit = 100 | 500 | 1000 | 5000 | "all";

interface ExportButtonProps {
  filters: ReportFiltersState;
  onAsyncExport: (exportId: string) => void;
}

export const ExportButton: React.FC<ExportButtonProps> = ({ filters, onAsyncExport }) => {
  const [limit, setLimit] = useState<ExportLimit>(1000);
  const [busy, setBusy] = useState(false);

  const doExport = async (type: "csv" | "pdf-print" | "pdf-server") => {
    setBusy(true);
    try {
      const params = buildReportQueryParams({ ...filters, export_limit: limit });

      if (type === "pdf-print") {
        window.open(route("reports.export-pdf") + "?" + new URLSearchParams(params as Record<string, string>).toString(), "_blank");
        return;
      }

      if (type === "pdf-server") {
        window.location.href = route("reports.export-pdf-download") + "?" + new URLSearchParams(params as Record<string, string>).toString();
        return;
      }

      // CSV — may return 202 for async
      const response = await axios.get(route("reports.export-csv"), {
        params,
        responseType: "blob",
        validateStatus: (s) => s === 200 || s === 202,
      });

      if (response.status === 202) {
        const exportId = (response.data as { export_id?: string }).export_id;
        if (exportId) {
          onAsyncExport(exportId);
          toast.info("Large export queued. Check the Exports tab for download status.");
        }
        return;
      }

      // Trigger browser download from blob
      const url = URL.createObjectURL(response.data as Blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `report-${Date.now()}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      toast.error("Export failed. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="flex items-center gap-2">
      <Select
        value={String(limit)}
        onValueChange={(v) => setLimit(v === "all" ? "all" : (Number(v) as ExportLimit))}
      >
        <SelectTrigger className="w-28 h-8 text-sm">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {([100, 500, 1000, 5000] as const).map((n) => (
            <SelectItem key={n} value={String(n)}>
              {n.toLocaleString()} rows
            </SelectItem>
          ))}
          <SelectItem value="all">All rows</SelectItem>
        </SelectContent>
      </Select>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" size="sm" disabled={busy} className="gap-1">
            <Download className="h-4 w-4" />
            Export
            <ChevronDown className="h-3 w-3" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onClick={() => doExport("csv")}>Download CSV</DropdownMenuItem>
          <DropdownMenuItem onClick={() => doExport("pdf-print")}>Download PDF (print)</DropdownMenuItem>
          <DropdownMenuItem onClick={() => doExport("pdf-server")}>Download PDF (server)</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
};
```

- [ ] **Step 5.3: Create `ScheduledExportModal.tsx`**

Create/edit form for scheduled exports. When `prefillFilters` is provided (the user opened it from the Data tab), pre-populates filter state as a snapshot.

```tsx
import React, { useEffect, useState } from "react";
import axios from "axios";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { toast } from "sonner";
import { ReportFiltersState, ScheduledExport, FilterStateSnapshot } from "../types";

type Frequency = "daily" | "weekly" | "monthly";
type ExportType = "csv" | "pdf";

interface ScheduledExportModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  formId: number;
  existing?: ScheduledExport | null;
  prefillFilters?: ReportFiltersState | null;
  onSaved: (export_: ScheduledExport) => void;
}

export const ScheduledExportModal: React.FC<ScheduledExportModalProps> = ({
  open,
  onOpenChange,
  formId,
  existing,
  prefillFilters,
  onSaved,
}) => {
  const [email, setEmail] = useState("");
  const [frequency, setFrequency] = useState<Frequency>("weekly");
  const [exportType, setExportType] = useState<ExportType>("csv");
  const [isActive, setIsActive] = useState(true);
  const [filterSnapshot, setFilterSnapshot] = useState<FilterStateSnapshot | null>(null);
  const [saving, setSaving] = useState(false);

  // Populate form when modal opens
  useEffect(() => {
    if (!open) return;
    if (existing) {
      setEmail(existing.recipient_email);
      setFrequency(existing.frequency);
      setExportType(existing.export_type);
      setIsActive(existing.is_active);
      setFilterSnapshot(existing.filter_state as FilterStateSnapshot | null);
    } else {
      setEmail("");
      setFrequency("weekly");
      setExportType("csv");
      setIsActive(true);
      // Copy current Data tab filters as snapshot (if provided)
      if (prefillFilters) {
        const { form_id, page, per_page, ...rest } = prefillFilters;
        void form_id; void page; void per_page; // intentionally excluded
        setFilterSnapshot(rest as FilterStateSnapshot);
      } else {
        setFilterSnapshot(null);
      }
    }
  }, [open, existing, prefillFilters]);

  const handleSave = async () => {
    if (!email.trim()) {
      toast.error("Recipient email is required.");
      return;
    }
    setSaving(true);
    try {
      const payload = {
        form_id: formId,
        recipient_email: email.trim(),
        frequency,
        export_type: exportType,
        is_active: isActive,
        filter_state: filterSnapshot,
      };

      const { data } = existing
        ? await axios.put<ScheduledExport>(route("reports.scheduled-exports.update", existing.id), payload)
        : await axios.post<ScheduledExport>(route("reports.scheduled-exports.store"), payload);

      onSaved(data);
      onOpenChange(false);
      toast.success(existing ? "Schedule updated." : "Schedule created.");
    } catch {
      toast.error("Could not save schedule. Please check your inputs.");
    } finally {
      setSaving(false);
    }
  };

  const hasFilters =
    filterSnapshot !== null &&
    (filterSnapshot.filters.length > 0 ||
      filterSnapshot.date_from ||
      filterSnapshot.submission_status);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{existing ? "Edit schedule" : "New scheduled export"}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-1">
            <Label>Recipient email</Label>
            <Input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="reports@company.com"
              autoFocus
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label>Frequency</Label>
              <Select value={frequency} onValueChange={(v) => setFrequency(v as Frequency)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="daily">Daily</SelectItem>
                  <SelectItem value="weekly">Weekly</SelectItem>
                  <SelectItem value="monthly">Monthly</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <Label>Format</Label>
              <Select value={exportType} onValueChange={(v) => setExportType(v as ExportType)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="csv">CSV</SelectItem>
                  <SelectItem value="pdf">PDF</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {hasFilters && (
            <div className="rounded border bg-muted/40 px-3 py-2 text-sm text-muted-foreground flex items-center justify-between">
              <span>Filters from current view will be saved with this schedule.</span>
              <Button variant="ghost" size="sm" onClick={() => setFilterSnapshot(null)}>
                Clear
              </Button>
            </div>
          )}

          {existing && (
            <div className="flex items-center justify-between">
              <Label>Active</Label>
              <Switch checked={isActive} onCheckedChange={setIsActive} />
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saving}>
            {saving ? "Saving…" : existing ? "Update" : "Create"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};
```

- [ ] **Step 5.4: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

- [ ] **Step 5.5: Commit**

```bash
git add resources/js/pages/Reports/components/SubmissionTable.tsx \
        resources/js/pages/Reports/components/ExportButton.tsx \
        resources/js/pages/Reports/components/ScheduledExportModal.tsx
git commit -m "feat(reports): add SubmissionTable, ExportButton, ScheduledExportModal components"
```

---

## Task 6: `OverviewTab`

**Files:**
- Create: `resources/js/pages/Reports/tabs/OverviewTab.tsx`

- [ ] **Step 6.1: Create `OverviewTab.tsx`**

Fetches chart data from `GET /reports/chart-data` and renders KPI cards + three charts. Clicking a trend chart point navigates to the Data tab with that date pre-set.

```tsx
import React, { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, Legend,
  BarChart, Bar,
} from "recharts";
import { KpiCard } from "../components/KpiCard";
import { ChartDataResponse, ReportTab } from "../types";

const STATUS_COLORS: Record<string, string> = {
  approved: "#22c55e",
  pending:  "#f59e0b",
  rejected: "#ef4444",
  completed: "#3b82f6",
};

interface OverviewTabProps {
  formId: number;
  onNavigateToData: (dateFrom: string, dateTo: string) => void;
}

export const OverviewTab: React.FC<OverviewTabProps> = ({ formId, onNavigateToData }) => {
  const [dateFrom, setDateFrom] = useState<string>(() => {
    const d = new Date();
    d.setDate(d.getDate() - 30);
    return d.toISOString().slice(0, 10);
  });
  const [dateTo, setDateTo] = useState<string>(new Date().toISOString().slice(0, 10));
  const [fieldKey, setFieldKey] = useState<string | null>(null);
  const [data, setData] = useState<ChartDataResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const fetchData = useCallback(() => {
    abortRef.current?.abort();
    abortRef.current = new AbortController();
    setLoading(true);
    setError(null);
    axios
      .get<ChartDataResponse>(route("reports.chart-data"), {
        params: { form_id: formId, date_from: dateFrom, date_to: dateTo, field_key: fieldKey },
        signal: abortRef.current.signal,
      })
      .then((r) => {
        setData(r.data);
        if (!fieldKey && r.data.field_distribution_column) {
          setFieldKey(r.data.field_distribution_column);
        }
      })
      .catch((e) => {
        if (!axios.isCancel(e)) setError("Could not load chart data.");
      })
      .finally(() => setLoading(false));
  }, [formId, dateFrom, dateTo, fieldKey]);

  useEffect(() => {
    fetchData();
    return () => abortRef.current?.abort();
  }, [fetchData]);

  return (
    <div className="space-y-6">
      {/* Date range controls */}
      <div className="flex flex-wrap items-end gap-3">
        <div className="flex flex-col gap-1">
          <Label className="text-xs text-muted-foreground">From</Label>
          <Input type="date" className="w-36 h-8 text-sm" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
        </div>
        <div className="flex flex-col gap-1">
          <Label className="text-xs text-muted-foreground">To</Label>
          <Input type="date" className="w-36 h-8 text-sm" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
        </div>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* KPI row */}
      <div className="flex flex-wrap gap-3">
        <KpiCard label="Total Submissions" value={data?.kpi.total_submissions ?? null} loading={loading} />
        <KpiCard label="Approved"          value={data?.kpi.approved ?? null}           loading={loading} />
        <KpiCard label="Pending"           value={data?.kpi.pending ?? null}            loading={loading} />
        <KpiCard label="Avg. Completion"   value={data?.kpi.avg_completion_human ?? null} loading={loading} />
      </div>

      {/* Charts row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Trend chart */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Submission Trend</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="h-48 rounded bg-muted animate-pulse" />
            ) : (
              <ResponsiveContainer width="100%" height={200}>
                <LineChart data={data?.trend ?? []} onClick={(e) => {
                  if (e?.activePayload?.[0]?.payload?.date) {
                    const d = e.activePayload[0].payload.date as string;
                    onNavigateToData(d, d);
                  }
                }}>
                  <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} />
                  <Tooltip />
                  <Line type="monotone" dataKey="count" stroke="#3b82f6" dot={false} strokeWidth={2} />
                </LineChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        {/* Status donut */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Status Breakdown</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="h-48 rounded bg-muted animate-pulse" />
            ) : (
              <ResponsiveContainer width="100%" height={200}>
                <PieChart>
                  <Pie
                    data={data?.status_breakdown ?? []}
                    dataKey="count"
                    nameKey="status"
                    cx="50%"
                    cy="50%"
                    outerRadius={70}
                    innerRadius={35}
                  >
                    {(data?.status_breakdown ?? []).map((entry) => (
                      <Cell key={entry.status} fill={STATUS_COLORS[entry.status] ?? "#94a3b8"} />
                    ))}
                  </Pie>
                  <Legend formatter={(v) => String(v)} />
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Field distribution */}
      <Card>
        <CardHeader className="pb-2 flex flex-row items-center justify-between">
          <CardTitle className="text-sm font-medium">Field Distribution</CardTitle>
          {data && data.available_field_columns.length > 0 && (
            <Select value={fieldKey ?? ""} onValueChange={(v) => setFieldKey(v || null)}>
              <SelectTrigger className="w-44 h-7 text-xs">
                <SelectValue placeholder="Select field" />
              </SelectTrigger>
              <SelectContent>
                {data.available_field_columns.map((f) => (
                  <SelectItem key={f.key} value={f.key}>{f.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="h-48 rounded bg-muted animate-pulse" />
          ) : !data || data.field_distribution.length === 0 ? (
            <p className="text-sm text-muted-foreground py-8 text-center">
              {data?.available_field_columns.length === 0
                ? "No categorical fields on this form."
                : "No data for selected field."}
            </p>
          ) : (
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={data.field_distribution} layout="vertical">
                <XAxis type="number" tick={{ fontSize: 11 }} />
                <YAxis type="category" dataKey="value" width={120} tick={{ fontSize: 11 }} />
                <Tooltip />
                <Bar dataKey="count" fill="#3b82f6" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
```

- [ ] **Step 6.2: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

- [ ] **Step 6.3: Commit**

```bash
git add resources/js/pages/Reports/tabs/OverviewTab.tsx
git commit -m "feat(reports): add OverviewTab with KPI cards and charts"
```

---

## Task 7: `DataTab`

**Files:**
- Create: `resources/js/pages/Reports/tabs/DataTab.tsx`

- [ ] **Step 7.1: Create `DataTab.tsx`**

Fetches submission data via `GET /reports/form-submissions`, renders FilterBar + SavedViewsPills + AdvancedFilters + ColumnSelector + SubmissionTable + ExportButton. Calls `onAsyncExport` when an export is queued (triggers Exports tab badge).

```tsx
import React, { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { RefreshCw } from "lucide-react";
import { buildReportQueryParams } from "../queryBuilder";
import { useReportFilters } from "../hooks/useReportFilters";
import {
  ReportBuilderCapabilities,
  ReportColumn,
  ReportData,
  ReportFiltersState,
  ReportPagination,
  ReportSubmission,
  ReportView,
} from "../types";
import { FilterBar } from "../components/FilterBar";
import { AdvancedFilters } from "../components/AdvancedFilters";
import { SavedViewsPills } from "../components/SavedViewsPills";
import { ColumnSelector } from "../components/ColumnSelector";
import { SubmissionTable } from "../components/SubmissionTable";
import { ExportButton } from "../components/ExportButton";

interface DataTabProps {
  formId: number;
  onAsyncExport: (exportId: string) => void;
  onFiltersChange?: (filters: ReportFiltersState) => void;
}

const DEFAULT_FILTERS = (formId: number): ReportFiltersState => ({
  form_id: formId,
  date_from: null,
  date_to: null,
  submission_status: "",
  account_id: null,
  submitter: null,
  select: [],
  filters: [],
  sort: null,
  per_page: 25,
  page: 1,
});

export const DataTab: React.FC<DataTabProps> = ({ formId, onAsyncExport, onFiltersChange }) => {
  const [filters, setFilters] = useState<ReportFiltersState>(() => DEFAULT_FILTERS(formId));
  const [submissions, setSubmissions] = useState<ReportSubmission[]>([]);
  const [pagination, setPagination] = useState<ReportPagination>({
    current_page: 1, last_page: 1, per_page: 25, total: 0,
  });
  const [columns, setColumns] = useState<ReportColumn[]>([]);
  const [availableColumns, setAvailableColumns] = useState<ReportColumn[]>([]);
  const [capabilities, setCapabilities] = useState<ReportBuilderCapabilities | null>(null);
  const [views, setViews] = useState<ReportView[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const fetchData = useCallback((overrideFilters?: ReportFiltersState) => {
    const active = overrideFilters ?? filters;
    abortRef.current?.abort();
    abortRef.current = new AbortController();
    setLoading(true);
    setError(null);

    axios
      .get<ReportData>(route("reports.form-submissions"), {
        params: buildReportQueryParams(active),
        signal: abortRef.current.signal,
      })
      .then((r) => {
        setSubmissions(r.data.submissions);
        setPagination(r.data.pagination);
        setColumns(r.data.columns);
        if (r.data.available_columns) setAvailableColumns(r.data.available_columns);
        if (r.data.builder) setCapabilities(r.data.builder);
      })
      .catch((e) => {
        if (!axios.isCancel(e)) setError("Could not load submissions. Please try again.");
      })
      .finally(() => setLoading(false));
  }, [filters]);

  // Fetch saved views once on mount
  useEffect(() => {
    axios
      .get<ReportView[]>(route("reports.views.index"), { params: { form_id: formId } })
      .then((r) => setViews(r.data))
      .catch(() => {/* non-fatal */});
  }, [formId]);

  // Re-fetch whenever filters change (debounced via useEffect dependency)
  useEffect(() => {
    fetchData();
    onFiltersChange?.(filters);
    return () => abortRef.current?.abort();
  }, [filters]);  // eslint-disable-line react-hooks/exhaustive-deps

  const patchFilters = (patch: Partial<ReportFiltersState>) => {
    setFilters((prev) => ({ ...prev, ...patch, page: patch.page ?? 1 }));
  };

  return (
    <div className="space-y-4">
      {/* Saved views */}
      <SavedViewsPills
        views={views}
        currentFilters={filters}
        onLoad={(f) => setFilters({ ...f, form_id: formId })}
        onViewsChange={setViews}
      />

      {/* Filter bar */}
      <FilterBar filters={filters} onChange={patchFilters} />

      {/* Advanced filters (collapsed) */}
      {capabilities && (
        <AdvancedFilters
          filters={filters.filters}
          capabilities={capabilities}
          onChange={(f) => patchFilters({ filters: f, page: 1 })}
        />
      )}

      {/* Toolbar row: column selector + export */}
      <div className="flex items-center justify-between">
        <ColumnSelector
          available={availableColumns}
          selected={filters.select.length > 0 ? filters.select : columns.map((c) => c.key)}
          onChange={(sel) => patchFilters({ select: sel })}
        />
        <ExportButton filters={filters} onAsyncExport={onAsyncExport} />
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertDescription className="flex items-center gap-2">
            {error}
            <Button variant="ghost" size="sm" onClick={() => fetchData()}>
              <RefreshCw className="h-3 w-3 mr-1" /> Retry
            </Button>
          </AlertDescription>
        </Alert>
      )}

      <SubmissionTable
        columns={columns}
        submissions={submissions}
        pagination={pagination}
        onPageChange={(p) => patchFilters({ page: p })}
        loading={loading}
      />
    </div>
  );
};
```

- [ ] **Step 7.2: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

- [ ] **Step 7.3: Commit**

```bash
git add resources/js/pages/Reports/tabs/DataTab.tsx
git commit -m "feat(reports): add DataTab"
```

---

## Task 8: `ExportsTab`

**Files:**
- Create: `resources/js/pages/Reports/tabs/ExportsTab.tsx`

- [ ] **Step 8.1: Create `ExportsTab.tsx`**

Polls active export status and manages the scheduled exports CRUD list. `activeExportId` is driven by the parent shell (set when `ExportButton` queues a job).

```tsx
import React, { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/components/ui/table";
import { Switch } from "@/components/ui/switch";
import { Download, Pencil, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { AsyncExportStatus, ReportFiltersState, ScheduledExport } from "../types";
import { ScheduledExportModal } from "../components/ScheduledExportModal";

const POLL_INTERVAL_MS = 3000;

const STATUS_BADGE: Record<string, { label: string; variant: "default" | "secondary" | "outline" | "destructive" }> = {
  queued:     { label: "Queued",     variant: "secondary" },
  processing: { label: "Processing", variant: "default" },
  completed:  { label: "Ready",      variant: "outline" },
  failed:     { label: "Failed",     variant: "destructive" },
};

interface ExportsTabProps {
  formId: number;
  activeExportId: string | null;
  onExportIdChange: (id: string | null) => void;
  dataTabFilters?: ReportFiltersState | null;
}

export const ExportsTab: React.FC<ExportsTabProps> = ({
  formId,
  activeExportId,
  onExportIdChange,
  dataTabFilters,
}) => {
  const [exportStatus, setExportStatus] = useState<AsyncExportStatus | null>(null);
  const [scheduledExports, setScheduledExports] = useState<ScheduledExport[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingExport, setEditingExport] = useState<ScheduledExport | null>(null);
  const pollTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Poll active export
  const pollExport = useCallback((exportId: string) => {
    axios
      .get<AsyncExportStatus>(route("reports.exports.status", exportId))
      .then((r) => {
        setExportStatus(r.data);
        if (r.data.status === "queued" || r.data.status === "processing") {
          pollTimerRef.current = setTimeout(() => pollExport(exportId), POLL_INTERVAL_MS);
        }
      })
      .catch(() => {
        setExportStatus(null);
        onExportIdChange(null);
      });
  }, [onExportIdChange]);

  useEffect(() => {
    if (activeExportId) {
      pollExport(activeExportId);
    } else {
      setExportStatus(null);
    }
    return () => {
      if (pollTimerRef.current) clearTimeout(pollTimerRef.current);
    };
  }, [activeExportId, pollExport]);

  // Load scheduled exports
  useEffect(() => {
    axios
      .get<ScheduledExport[]>(route("reports.scheduled-exports.index"), { params: { form_id: formId } })
      .then((r) => setScheduledExports(r.data))
      .catch(() => {/* non-fatal */});
  }, [formId]);

  const handleToggleActive = async (export_: ScheduledExport) => {
    try {
      const { data } = await axios.put<ScheduledExport>(
        route("reports.scheduled-exports.update", export_.id),
        { is_active: !export_.is_active }
      );
      setScheduledExports((prev) => prev.map((e) => (e.id === data.id ? data : e)));
    } catch {
      toast.error("Could not update schedule.");
    }
  };

  const handleDelete = async (export_: ScheduledExport) => {
    if (!window.confirm(`Delete schedule "${export_.recipient_email} (${export_.frequency})"?`)) return;
    try {
      await axios.delete(route("reports.scheduled-exports.destroy", export_.id));
      setScheduledExports((prev) => prev.filter((e) => e.id !== export_.id));
      toast.success("Schedule deleted.");
    } catch {
      toast.error("Could not delete schedule.");
    }
  };

  const handleSaved = (saved: ScheduledExport) => {
    setScheduledExports((prev) => {
      const idx = prev.findIndex((e) => e.id === saved.id);
      return idx >= 0 ? prev.map((e) => (e.id === saved.id ? saved : e)) : [...prev, saved];
    });
  };

  return (
    <div className="space-y-6">
      {/* Active async export card */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium">Active Export</CardTitle>
        </CardHeader>
        <CardContent>
          {!activeExportId || !exportStatus ? (
            <p className="text-sm text-muted-foreground">No active export.</p>
          ) : (
            <div className="flex items-center gap-4">
              <Badge variant={STATUS_BADGE[exportStatus.status]?.variant ?? "outline"}>
                {STATUS_BADGE[exportStatus.status]?.label ?? exportStatus.status}
              </Badge>
              {exportStatus.filename && (
                <span className="text-sm text-muted-foreground">{exportStatus.filename}</span>
              )}
              {exportStatus.status === "completed" && (
                <Button asChild size="sm" variant="outline">
                  <a href={route("reports.exports.download", exportStatus.export_id)}>
                    <Download className="h-4 w-4 mr-1" /> Download
                  </a>
                </Button>
              )}
              {exportStatus.status === "failed" && (
                <span className="text-sm text-destructive">{exportStatus.error ?? "Export failed."}</span>
              )}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Scheduled exports */}
      <Card>
        <CardHeader className="pb-2 flex flex-row items-center justify-between">
          <CardTitle className="text-sm font-medium">Scheduled Exports</CardTitle>
          <Button
            size="sm"
            variant="outline"
            className="gap-1"
            onClick={() => { setEditingExport(null); setModalOpen(true); }}
          >
            <Plus className="h-4 w-4" /> New schedule
          </Button>
        </CardHeader>
        <CardContent className="p-0">
          {scheduledExports.length === 0 ? (
            <p className="text-sm text-muted-foreground p-4">No scheduled exports for this form.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Email</TableHead>
                  <TableHead>Frequency</TableHead>
                  <TableHead>Format</TableHead>
                  <TableHead>Last sent</TableHead>
                  <TableHead>Active</TableHead>
                  <TableHead className="w-16" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {scheduledExports.map((e) => (
                  <TableRow key={e.id}>
                    <TableCell>{e.recipient_email}</TableCell>
                    <TableCell>
                      <Badge variant="secondary">{e.frequency}</Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline">{e.export_type.toUpperCase()}</Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground text-sm">
                      {e.last_sent_at ? new Date(e.last_sent_at).toLocaleDateString() : "Never"}
                    </TableCell>
                    <TableCell>
                      <Switch
                        checked={e.is_active}
                        onCheckedChange={() => handleToggleActive(e)}
                        aria-label={`Toggle ${e.recipient_email} schedule`}
                      />
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7"
                          onClick={() => { setEditingExport(e); setModalOpen(true); }}
                          aria-label="Edit schedule"
                        >
                          <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7 text-destructive hover:text-destructive"
                          onClick={() => handleDelete(e)}
                          aria-label="Delete schedule"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <ScheduledExportModal
        open={modalOpen}
        onOpenChange={setModalOpen}
        formId={formId}
        existing={editingExport}
        prefillFilters={editingExport ? null : dataTabFilters}
        onSaved={handleSaved}
      />
    </div>
  );
};
```

- [ ] **Step 8.2: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

- [ ] **Step 8.3: Commit**

```bash
git add resources/js/pages/Reports/tabs/ExportsTab.tsx
git commit -m "feat(reports): add ExportsTab with async export polling and scheduled exports CRUD"
```

---

## Task 9: `CompareTab`

**Files:**
- Create: `resources/js/pages/Reports/tabs/CompareTab.tsx`

- [ ] **Step 9.1: Create `CompareTab.tsx`**

Cross-form comparison (top) + aggregation tool (bottom). Comparison uses its own multi-form picker independent of the top-level form. Aggregation uses the top-level `formId`.

```tsx
import React, { useEffect, useState } from "react";
import axios from "axios";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/components/ui/table";
import { Separator } from "@/components/ui/separator";
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
} from "recharts";
import { ReportColumn, ReportForm } from "../types";

type CompareMetric = "submission_count" | "avg_completion_time" | "approval_rate";
type AggFunction = "count" | "sum" | "avg" | "min" | "max";

interface CompareResult {
  form_name: string;
  value: number | string;
}

interface AggResult {
  group_value: string | null;
  aggregate_value: number | string | null;
}

interface CompareTabProps {
  /** Top-level selected form (used by aggregation tool only). null means none selected. */
  formId: number | null;
}

const METRICS: { value: CompareMetric; label: string }[] = [
  { value: "submission_count",  label: "Submission Count" },
  { value: "avg_completion_time", label: "Avg. Completion Time" },
  { value: "approval_rate",     label: "Approval Rate" },
];

const AGG_FUNCTIONS: { value: AggFunction; label: string }[] = [
  { value: "count", label: "Count" },
  { value: "sum",   label: "Sum" },
  { value: "avg",   label: "Avg" },
  { value: "min",   label: "Min" },
  { value: "max",   label: "Max" },
];

export const CompareTab: React.FC<CompareTabProps> = ({ formId }) => {
  // Cross-form comparison state
  const [allForms, setAllForms] = useState<ReportForm[]>([]);
  const [selectedFormIds, setSelectedFormIds] = useState<number[]>([]);
  const [metric, setMetric] = useState<CompareMetric>("submission_count");
  const [compareFrom, setCompareFrom] = useState("");
  const [compareTo, setCompareTo] = useState("");
  const [compareResults, setCompareResults] = useState<CompareResult[] | null>(null);
  const [compareLoading, setCompareLoading] = useState(false);
  const [compareError, setCompareError] = useState<string | null>(null);

  // Aggregation state
  const [aggColumns, setAggColumns] = useState<ReportColumn[]>([]);
  const [groupBy, setGroupBy] = useState<string>("");
  const [aggFunction, setAggFunction] = useState<AggFunction>("count");
  const [aggColumn, setAggColumn] = useState<string>("");
  const [aggResults, setAggResults] = useState<AggResult[] | null>(null);
  const [aggLoading, setAggLoading] = useState(false);
  const [aggError, setAggError] = useState<string | null>(null);

  // Load all forms for comparison picker
  useEffect(() => {
    axios.get<ReportForm[]>(route("reports.forms")).then((r) => setAllForms(r.data)).catch(() => {});
  }, []);

  // Load filterable columns when formId changes (for aggregation)
  useEffect(() => {
    if (!formId) return;
    axios
      .get<{ builder: { filterable_columns: ReportColumn[] } }>(
        route("reports.form-submissions"),
        { params: { form_id: formId, per_page: 1 } }
      )
      .then((r) => setAggColumns(r.data.builder?.filterable_columns ?? []))
      .catch(() => {});
  }, [formId]);

  const toggleForm = (id: number) => {
    setSelectedFormIds((prev) =>
      prev.includes(id)
        ? prev.filter((f) => f !== id)
        : prev.length < 10 ? [...prev, id] : prev
    );
  };

  const runComparison = async () => {
    setCompareLoading(true);
    setCompareError(null);
    try {
      const { data } = await axios.get<{ data: CompareResult[] }>(route("reports.compare"), {
        params: {
          form_ids: selectedFormIds,
          metric,
          date_from: compareFrom || undefined,
          date_to: compareTo || undefined,
        },
      });
      setCompareResults(data.data);
    } catch {
      setCompareError("Could not run comparison.");
    } finally {
      setCompareLoading(false);
    }
  };

  const runAggregation = async () => {
    if (!formId || !groupBy) return;
    setAggLoading(true);
    setAggError(null);
    try {
      const { data } = await axios.get<{ data: AggResult[] }>(route("reports.aggregate"), {
        params: {
          form_id: formId,
          group_by: groupBy,
          function: aggFunction,
          column: aggFunction !== "count" ? aggColumn : undefined,
        },
      });
      setAggResults(data.data);
    } catch {
      setAggError("Could not compute aggregation.");
    } finally {
      setAggLoading(false);
    }
  };

  const numericFunctions: AggFunction[] = ["sum", "avg", "min", "max"];

  return (
    <div className="space-y-8">
      {/* Cross-form comparison */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium">Cross-Form Comparison</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <Label className="text-xs text-muted-foreground mb-2 block">
              Select forms to compare (max 10)
            </Label>
            <div className="flex flex-wrap gap-2 max-h-36 overflow-y-auto">
              {allForms.map((f) => (
                <button
                  key={f.id}
                  onClick={() => toggleForm(f.id)}
                  className={`rounded-full border px-3 py-1 text-sm transition-colors ${
                    selectedFormIds.includes(f.id)
                      ? "bg-primary text-primary-foreground border-primary"
                      : "hover:bg-muted"
                  }`}
                >
                  {f.form_name}
                </button>
              ))}
            </div>
          </div>

          <div className="flex flex-wrap items-end gap-3">
            <div className="flex flex-col gap-1">
              <Label className="text-xs text-muted-foreground">Metric</Label>
              <Select value={metric} onValueChange={(v) => setMetric(v as CompareMetric)}>
                <SelectTrigger className="w-48 h-8 text-sm">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {METRICS.map((m) => (
                    <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-1">
              <Label className="text-xs text-muted-foreground">From</Label>
              <Input type="date" className="w-36 h-8 text-sm" value={compareFrom} onChange={(e) => setCompareFrom(e.target.value)} />
            </div>
            <div className="flex flex-col gap-1">
              <Label className="text-xs text-muted-foreground">To</Label>
              <Input type="date" className="w-36 h-8 text-sm" value={compareTo} onChange={(e) => setCompareTo(e.target.value)} />
            </div>
            <Button
              size="sm"
              onClick={runComparison}
              disabled={selectedFormIds.length < 2 || compareLoading}
            >
              {compareLoading ? "Running…" : "Run Comparison"}
            </Button>
          </div>

          {compareError && (
            <Alert variant="destructive"><AlertDescription>{compareError}</AlertDescription></Alert>
          )}

          {compareResults && (
            <div className="space-y-4">
              <ResponsiveContainer width="100%" height={200}>
                <BarChart data={compareResults}>
                  <XAxis dataKey="form_name" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} />
                  <Tooltip />
                  <Bar dataKey="value" fill="#3b82f6" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Form</TableHead>
                    <TableHead>{METRICS.find((m) => m.value === metric)?.label}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {compareResults.map((r) => (
                    <TableRow key={r.form_name}>
                      <TableCell>{r.form_name}</TableCell>
                      <TableCell>{r.value}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <Separator />

      {/* Aggregation */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium">Aggregation</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {!formId ? (
            <p className="text-sm text-muted-foreground">Select a form using the top picker to use the aggregation tool.</p>
          ) : (
            <>
              <div className="flex flex-wrap items-end gap-3">
                <div className="flex flex-col gap-1">
                  <Label className="text-xs text-muted-foreground">Group by</Label>
                  <Select value={groupBy} onValueChange={setGroupBy}>
                    <SelectTrigger className="w-44 h-8 text-sm">
                      <SelectValue placeholder="Select column" />
                    </SelectTrigger>
                    <SelectContent>
                      {aggColumns.map((c) => (
                        <SelectItem key={c.key} value={c.key}>{c.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="flex flex-col gap-1">
                  <Label className="text-xs text-muted-foreground">Function</Label>
                  <Select value={aggFunction} onValueChange={(v) => setAggFunction(v as AggFunction)}>
                    <SelectTrigger className="w-28 h-8 text-sm">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {AGG_FUNCTIONS.map((f) => (
                        <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {numericFunctions.includes(aggFunction) && (
                  <div className="flex flex-col gap-1">
                    <Label className="text-xs text-muted-foreground">Column</Label>
                    <Select value={aggColumn} onValueChange={setAggColumn}>
                      <SelectTrigger className="w-44 h-8 text-sm">
                        <SelectValue placeholder="Select column" />
                      </SelectTrigger>
                      <SelectContent>
                        {aggColumns
                          .filter((c) => c.type === "number" || c.type === "integer")
                          .map((c) => (
                            <SelectItem key={c.key} value={c.key}>{c.label}</SelectItem>
                          ))}
                      </SelectContent>
                    </Select>
                  </div>
                )}

                <Button
                  size="sm"
                  onClick={runAggregation}
                  disabled={!groupBy || aggLoading || (numericFunctions.includes(aggFunction) && !aggColumn)}
                >
                  {aggLoading ? "Running…" : "Run Aggregation"}
                </Button>
              </div>

              {aggError && (
                <Alert variant="destructive"><AlertDescription>{aggError}</AlertDescription></Alert>
              )}

              {aggResults && (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Group</TableHead>
                      <TableHead>{aggFunction.toUpperCase()}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {aggResults.map((r, i) => (
                      <TableRow key={i}>
                        <TableCell>{r.group_value ?? "(empty)"}</TableCell>
                        <TableCell>{r.aggregate_value}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
```

- [ ] **Step 9.2: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

- [ ] **Step 9.3: Commit**

```bash
git add resources/js/pages/Reports/tabs/CompareTab.tsx
git commit -m "feat(reports): add CompareTab with cross-form comparison and aggregation"
```

---

## Task 10: Rewrite `ReportsPage.tsx` as shell

**Files:**
- Modify (rewrite): `resources/js/pages/Reports/ReportsPage.tsx`

- [ ] **Step 10.1: Rewrite `ReportsPage.tsx`**

Replace the entire file with:

```tsx
import React, { useCallback, useState } from "react";
import { router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { FormPicker } from "./components/FormPicker";
import { OverviewTab } from "./tabs/OverviewTab";
import { DataTab } from "./tabs/DataTab";
import { ExportsTab } from "./tabs/ExportsTab";
import { CompareTab } from "./tabs/CompareTab";
import { ReportFiltersState, ReportTab } from "./types";

interface Props {
  error?: string | null;
}

// Read initial form_id and tab from URL query params (Inertia preserves the URL)
const getInitialFormId = (): number | null => {
  const params = new URLSearchParams(window.location.search);
  const v = params.get("form_id");
  return v ? Number(v) : null;
};

const getInitialTab = (): ReportTab => {
  const params = new URLSearchParams(window.location.search);
  const t = params.get("tab");
  return (["overview", "data", "exports", "compare"] as ReportTab[]).includes(t as ReportTab)
    ? (t as ReportTab)
    : "overview";
};

const ReportsPage: React.FC<Props> = ({ error }) => {
  const [formId, setFormId] = useState<number | null>(getInitialFormId);
  const [activeTab, setActiveTab] = useState<ReportTab>(getInitialTab);
  const [asyncExportId, setAsyncExportId] = useState<string | null>(null);
  const [dataTabFilters, setDataTabFilters] = useState<ReportFiltersState | null>(null);

  // Keep URL in sync so tabs/form are shareable
  const updateUrl = useCallback((newFormId: number | null, newTab: ReportTab) => {
    const params = new URLSearchParams();
    if (newFormId) params.set("form_id", String(newFormId));
    params.set("tab", newTab);
    router.replace(route("reports.index") + "?" + params.toString(), { preserveState: true, preserveScroll: true });
  }, []);

  const handleFormChange = (id: number | null) => {
    setFormId(id);
    setActiveTab("overview");
    setAsyncExportId(null);
    updateUrl(id, "overview");
  };

  const handleTabChange = (tab: string) => {
    const t = tab as ReportTab;
    setActiveTab(t);
    updateUrl(formId, t);
  };

  const handleAsyncExport = (exportId: string) => {
    setAsyncExportId(exportId);
    handleTabChange("exports");
  };

  const handleNavigateToData = (dateFrom: string, dateTo: string) => {
    setDataTabFilters((prev) =>
      prev
        ? { ...prev, date_from: dateFrom, date_to: dateTo }
        : null
    );
    handleTabChange("data");
  };

  return (
    <AppLayout>
      <div className="flex flex-col gap-6 p-6 max-w-screen-2xl mx-auto">
        {/* Header */}
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold">Reports</h1>
          <FormPicker selectedFormId={formId} onChange={handleFormChange} />
        </div>

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {!formId ? (
          <div className="flex items-center justify-center py-24 text-muted-foreground">
            Select a form above to get started.
          </div>
        ) : (
          <Tabs value={activeTab} onValueChange={handleTabChange}>
            <TabsList>
              <TabsTrigger value="overview">Overview</TabsTrigger>
              <TabsTrigger value="data">Data</TabsTrigger>
              <TabsTrigger value="exports">
                Exports
                {asyncExportId && (
                  <span className="ml-1.5 h-2 w-2 rounded-full bg-primary inline-block" />
                )}
              </TabsTrigger>
              <TabsTrigger value="compare">Compare</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" className="mt-4">
              <OverviewTab formId={formId} onNavigateToData={handleNavigateToData} />
            </TabsContent>

            <TabsContent value="data" className="mt-4">
              <DataTab
                formId={formId}
                onAsyncExport={handleAsyncExport}
                onFiltersChange={setDataTabFilters}
              />
            </TabsContent>

            <TabsContent value="exports" className="mt-4">
              <ExportsTab
                formId={formId}
                activeExportId={asyncExportId}
                onExportIdChange={setAsyncExportId}
                dataTabFilters={dataTabFilters}
              />
            </TabsContent>

            <TabsContent value="compare" className="mt-4">
              <CompareTab formId={formId} />
            </TabsContent>
          </Tabs>
        )}
      </div>
    </AppLayout>
  );
};

export default ReportsPage;
```

- [ ] **Step 10.2: Verify no TypeScript errors**

```bash
npx tsc --noEmit
```

- [ ] **Step 10.3: Start dev server and visually verify the page loads**

```bash
npm run dev
```

Navigate to `/reports` in the browser. Confirm:
- Form picker appears
- Selecting a form shows the 4 tabs
- Overview tab loads (may show chart errors if backend not running — that is expected)
- Data, Exports, Compare tabs are navigable

Stop the dev server with Ctrl+C.

- [ ] **Step 10.4: Commit**

```bash
git add resources/js/pages/Reports/ReportsPage.tsx
git commit -m "feat(reports): rewrite ReportsPage as tab-organised shell"
```

---

## Task 11: Delete old components

Only delete once Task 10 compiles and the page is working.

- [ ] **Step 11.1: Delete the replaced components**

```bash
rm resources/js/pages/Reports/components/ReportHero.tsx
rm resources/js/pages/Reports/components/ReportFilters.tsx
rm resources/js/pages/Reports/components/ReportTable.tsx
rm resources/js/pages/Reports/components/ReportCharts.tsx
rm resources/js/pages/Reports/components/CompareFormsModal.tsx
rm resources/js/pages/Reports/components/ScheduledExportsModal.tsx
rm resources/js/pages/Reports/components/AggregationSection.tsx
rm resources/js/pages/Reports/components/ColumnPicker.tsx
rm resources/js/pages/Reports/components/ExportOptionsBar.tsx
rm resources/js/pages/Reports/components/FormFieldDistributionChart.tsx
rm resources/js/pages/Reports/components/ReportFormPicker.tsx
rm resources/js/pages/Reports/components/SavedReportsDropdown.tsx
rm resources/js/pages/Reports/components/SortSection.tsx
rm resources/js/pages/Reports/components/StatusBreakdownChart.tsx
rm resources/js/pages/Reports/components/SubmissionTrendChart.tsx
rm resources/js/pages/Reports/components/DatePickerInput.tsx
rm resources/js/pages/Reports/components/DateRangeSection.tsx
```

Note: `BuilderFilterList.tsx` is kept — it is imported by `AdvancedFilters.tsx`.

- [ ] **Step 11.2: Fix any resulting TypeScript errors**

```bash
npx tsc --noEmit
```

If any imports in retained files reference deleted components, update those imports now.

- [ ] **Step 11.3: Run the full test suite**

```bash
php artisan test
```

Expected: All PHP tests green. (Frontend tests in `__tests__/` may need updating if they import deleted components — update them to test the new tab components.)

- [ ] **Step 11.4: Commit**

```bash
git add -A
git commit -m "refactor(reports): delete replaced components after tab redesign"
```

---

## Final verification

- [ ] **TypeScript build**

```bash
npm run build
```

Expected: Build succeeds with no errors.

- [ ] **PHP test suite**

```bash
php artisan test
```

Expected: All tests green.
