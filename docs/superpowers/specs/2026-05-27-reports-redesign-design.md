# Reports Feature Redesign

**Date:** 2026-05-27  
**Status:** Approved — ready for implementation planning  
**Scope:** Full UX/architecture redesign of `app/Modules/Reports/` + `resources/js/pages/Reports/`

---

## Background & Motivation

The existing Reports feature is a single-page data dump that buries analytics insights under a complex filter builder, mixes two distinct usage modes (data auditing vs. overview analytics) on one screen, and has a clunky export flow. A security audit also surfaced critical issues in the attachment endpoints. This redesign addresses all of these together.

**Problems being fixed:**
- Table-first layout buries the analytics that managers actually want
- Query builder is too complex for everyday use; advanced options should be collapsed
- Export state (async queue, scheduled jobs) is mixed into the main report view
- Cross-form comparison and aggregation are hidden and underused
- IDOR vulnerability on attachment download/preview (no ownership check)
- `file_get_contents` on preview = memory DoS vector
- Untrusted `mime_type` from DB served directly as `Content-Type` (stored XSS)
- `filter_state` on scheduled exports bypasses request validation
- Monthly scheduled export uses `subDays(30)` instead of `subMonth()`
- Async export files accumulate on disk with no cleanup
- Cache key uses `serialize()` on user input (non-deterministic ordering)
- Export limit `5000` repeated as a magic number in two places
- Double `FormField` DB query per validated request

---

## Architecture

### URL & Routing

Single Inertia route at `/reports`, registered in `app/Modules/Reports/routes.php`. No new routes added.

URL shape: `/reports?form_id=42&tab=overview`

- `form_id` — the selected form; absent = empty state (form picker only)
- `tab` — `overview` (default) | `data` | `exports` | `compare`

Tab state lives in the URL so browser back/forward and shareable links work correctly.

### Backend

All 12 existing service classes are kept. The `ReportsController` Inertia index endpoint (`GET /reports`) no longer assembles the full report payload on first load — it renders the shell with `reportData: null`. Each tab fetches its own data independently via client-side axios calls to the existing JSON endpoints:

| Tab | Primary endpoint(s) |
|-----|---------------------|
| Overview | `GET /reports/chart-data` |
| Data | `GET /reports/form-submissions` |
| Exports | `GET /reports/exports/{id}`, `GET /reports/scheduled-exports` |
| Compare | `GET /reports/compare`, `GET /reports/aggregate` |

### Frontend File Structure

Replaces the current monolithic `ReportsPage.tsx` and its flat `components/` folder:

```
resources/js/pages/Reports/
├── ReportsPage.tsx               ← shell: form picker + tab nav only
├── tabs/
│   ├── OverviewTab.tsx           ← KPI cards + charts
│   ├── DataTab.tsx               ← filter bar + table + inline attachments
│   ├── ExportsTab.tsx            ← async status card + scheduled exports CRUD
│   └── CompareTab.tsx            ← cross-form comparison + aggregation tool
├── components/
│   ├── FormPicker.tsx            ← shared form selector (moved from ReportFormPicker)
│   ├── KpiCard.tsx               ← reusable stat card
│   ├── FilterBar.tsx             ← simplified top-level filter controls
│   ├── AdvancedFilters.tsx       ← collapsed query builder (column/operator/value rows)
│   ├── SavedViewsPills.tsx       ← saved views pill row + save/delete controls
│   ├── SubmissionTable.tsx       ← data table with expandable attachment rows
│   ├── ColumnSelector.tsx        ← column visibility picker
│   ├── ExportButton.tsx          ← CSV/PDF export with async flow handling
│   └── ScheduledExportModal.tsx  ← create/edit scheduled export form
├── hooks/
│   ├── useReportFilters.ts       ← filter state management
│   ├── useAsyncExport.ts         ← async export polling logic
│   └── useChartData.ts           ← chart data fetching
└── types.ts                      ← all Report-related TypeScript types
```

Existing components that have a direct successor are deleted; shared utility logic from `queryBuilder.ts` is kept and moved into `hooks/useReportFilters.ts`.

---

## Tab Specifications

### Overview Tab

Default tab when a form is selected.

**Controls (at top of tab):**
- Date range picker (from / to) — drives all cards and charts on this tab
- Defaults to last 30 days

**KPI row (4 cards):**
1. Total Submissions (count for date window)
2. Approved (count)
3. Pending (count)
4. Avg. Completion Time (human-formatted, e.g. "2d 4h")

Rejected count is visible in the Status Breakdown donut chart rather than a dedicated KPI card, to keep the card row to 4 items.

Numbers animate in on data load (simple CSS counter animation).

**Trend chart:**
- Line chart — daily submission count over the selected date window
- Clicking a data point navigates to the Data tab with `date_from` and `date_to` pre-set to that single day

**Status breakdown:**
- Donut chart — proportional view of submission statuses within the date window

**Field distribution:**
- Bar chart — top 10 values for a selected categorical field
- A dropdown above it lets the user pick any categorical field (checkbox, radio, select types)
- Defaults to the first categorical field found on the form
- "No categorical fields" empty state if none exist

**Data source:** `GET /reports/chart-data?form_id=X&date_from=Y&date_to=Z`

---

### Data Tab

**Filter bar (always visible):**
- Date range (from / to)
- Status dropdown (All / Pending / Approved / Rejected / Completed)
- Submitter search (free text)
- Column selector (multi-select pill list)
- Per-page selector (10 / 25 / 50 / 100)

**Saved views (above filter bar):**
- Pill row showing user's saved views for the current form
- Clicking a pill loads its `filter_state` into the filter bar
- "Save current view" button → modal with name input
- Delete icon on each pill (owner-only, confirmed)

**Advanced filters (collapsed by default):**
- Revealed by "Advanced filters ▾" toggle button
- Full query builder: column + operator + value rows with AND/OR logic
- Same capabilities as current builder, just hidden by default

**Submission table:**
- Renders selected columns
- Default sort: submitted_at descending
- Each row has an expand chevron (▶) that reveals:
  - Attachment list: thumbnail (image) or file icon (PDF/other), filename, "Download" and "Preview" links
- Pagination controls at the bottom

**Export (top-right of tab toolbar):**
- "Export" button → dropdown: "Download CSV" / "Download PDF (print)" / "Download PDF (server)"
- Async threshold logic unchanged: if row count > threshold → queue job, show status in Exports tab
- Export limit selector (100 / 500 / 1000 / 5000 / All) adjacent to Export button

**Data source:** `GET /reports/form-submissions` with full filter params

---

### Exports Tab

**Active export card (top half):**
- Shows current async export: status badge (Queued / Processing / Ready / Failed), filename, "Download" button when ready, "Retry" on failure
- Empty state: "No active export" when none exists
- Polling interval: 3 seconds while status is queued or processing

**Scheduled exports (bottom half):**
- List of the user's scheduled exports: form name, frequency badge, format badge, last sent timestamp, active toggle
- "New schedule" button → `ScheduledExportModal`
- Edit (pencil icon) and Delete (trash icon) per row, owner-scoped
- `ScheduledExportModal` fields: form selector, recipient email, frequency (Daily / Weekly / Monthly), export type (CSV / PDF), filter state (optional — if the user opens the modal while on the Data tab with active filters, those filters are copied into the schedule at creation time as a snapshot; they are not dynamically linked. The modal shows a summary of any pre-filled filters and allows clearing them.

---

### Compare Tab

**Cross-form comparison (top section):**
- Multi-select form picker (max 10 forms, validated server-side)
- Metric selector: Submission Count / Avg. Completion Time / Approval Rate
- Optional date range
- "Run Comparison" button → bar chart with one bar per form
- Results table below chart (form name + value)

**Aggregation tool (bottom section, separated by a divider):**
- Group-by column selector (dropdowns populated from selected form's filterable columns)
- Function selector (Count / Sum / Avg / Min / Max)
- Aggregate column selector (conditionally shown for non-count functions; restricted to numeric columns for Sum/Avg)
- "Run Aggregation" button → results table (group value + aggregate value, up to 500 rows)

**Note:** The aggregation tool uses the form selected in the top-level form picker for its column lists and data scope. The cross-form comparison tool has its own independent multi-form picker and does not depend on the top-level form picker selection. The top-level form picker is therefore not required to use the comparison section, but must be set to use aggregation.

---

## Backend Changes

### Security Fixes

**`ReportsController::downloadAttachment(int $id)`**
- After `SubmissionAttachment::findOrFail($id)`, verify `$attachment->submission->account_id === auth()->user()->account_id` OR user has `submissions.override` permission. Abort 403 if neither.
- Add `X-Content-Type-Options: nosniff` to response headers.

**`ReportsController::previewAttachment(int $id)`**
- Same ownership check as above.
- Replace `file_get_contents($fullPath)` with `Response::stream()` to avoid memory exhaustion on large files.
- Restrict `Content-Type` to an allowlist: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `application/pdf`. Anything else served as `application/octet-stream` with `Content-Disposition: attachment` (forces download, no inline render).
- `Content-Disposition: 'inline; filename="...'` — filename sanitized by using `Response::download()` mechanics rather than manual string concatenation.
- Add `X-Content-Type-Options: nosniff`.

**`StoreScheduledExportRequest` / `UpdateScheduledExportRequest`**
- `filter_state` validated as `nullable array` with max nesting depth of 3 (group → leaf). Any deeper structure is rejected with a validation error. Column names and operators within `filter_state` validated against the form's allowed columns/operators (same logic as `ReportsFilterRequest::withValidator()`).

**`ReportSummaryService::buildSummary()`**
- Cache key: `serialize($summaryFilters)` → `json_encode($summaryFilters, JSON_SORT_KEYS)`.

**`ReportsFilterRequest`**
- `resolveSelectableColumns()` and `resolveFormFieldTypes()` merged into a single `FormField::query()` call; both outputs derived from the same result collection.
- `MAX_EXPORT_LIMIT = 5000` class constant; used in both the `'max:5000'` rule and the `'all'` alias resolution.

### Correctness Fixes

**`ScheduledExportService::findDue()`**
- `$now->copy()->subDays(30)` → `$now->copy()->subMonth()` for monthly frequency.

### New Additions

**`app/Console/Commands/CleanupAsyncExports.php`**
- New artisan command: `reports:cleanup-exports`
- Deletes files under `storage/app/exports/async/` older than `config('reports.async_export_cache_ttl_seconds')` seconds.
- Registered in the scheduler to run hourly.
- Logs count of deleted files at `info` level.

**`config/reports.php`**
- No new keys needed; existing `async_export_cache_ttl_seconds` reused by cleanup command.

---

## Error Handling

- Each tab independently shows its own inline error alert on data fetch failure; errors in one tab do not affect others.
- Chart load errors show a "Could not load chart data" card in place of the chart.
- Table load errors show an inline alert with a retry button.
- Export errors continue to use the existing toast pattern.
- Attachment ownership check failures return 403; the frontend shows "You do not have access to this file."

---

## Testing

**Existing tests kept (contracts unchanged):**
`ReportsRowAccessPolicyTest`, `ReportsAsyncExportTest`, `ReportsPdfExportTest`, `ReportsBuilderFilterRoundTripTest`, `ReportsCanonicalReadTest`, `ReportsExportLimitTest`, `ReportsFormsListTest`, `ReportsNestedFilterTest`, `ReportsQueryBuilderContractTest`, `ReportsSavedViewTest`, `ReportsScheduledExportTest`, `ReportsAggregationTest`, `ReportsAsyncExportStatusTest`

**New tests:**
- `ReportsAttachmentAccessTest` — IDOR fix: own attachment downloadable, other user's attachment returns 403, override user can access any.
- `ReportsAttachmentMimeAllowlistTest` — preview of HTML/JS MIME type returns `application/octet-stream` + attachment disposition.
- `ReportsScheduledExportFilterStateValidationTest` — deeply nested / invalid column filter_state rejected on store/update.
- `ReportsCleanupCommandTest` — cleanup command deletes files older than TTL, leaves newer ones untouched.
- `ReportsMonthlyFrequencyTest` — `findDue()` does not return a monthly export sent 28 days ago; does return one sent 32 days ago.
