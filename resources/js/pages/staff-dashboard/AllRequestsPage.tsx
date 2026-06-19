import React, { useMemo, useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import SearchWithFilters, { SearchFilterState } from "./components/SearchWithFilters";
import RequestsCards from "./components/RequestsCards"; // ⬅️ NEW

type Row = {
  progress_id: number;
  submission_id: number;
  id?: number;
  form_code: string;
  form_name: string;
  status: string;
  progress?: number;
  submitted_at: string;
  submitter: string;
  version?: number;
  is_latest?: boolean;
};

type RequestsProp = Row[] | { data: Row[]; meta?: Record<string, any> };

export default function AllRequestsPage() {
  const { requests } = usePage<{ requests: RequestsProp }>().props;

  // Guard: treat completely unexpected shapes as an empty list
  const isValidProp =
    Array.isArray(requests) ||
    (requests !== null &&
      typeof requests === "object" &&
      Array.isArray((requests as { data?: unknown }).data));

  // normalize to array and ensure we have both progress_id and submission_id
  const rows: Row[] = useMemo(() => {
    if (!isValidProp) return [];
    let data: any[] = [];
    if (Array.isArray(requests)) data = requests;
    else if (requests && Array.isArray((requests as any).data)) data = (requests as any).data;
    
    // Map to ensure correct structure
    return data.map((r: any) => ({
      progress_id: r.progress_id || r.id,
      submission_id: r.submission_id || r.id,
      form_code: r.form_code,
      form_name: r.form_name,
      status: r.status,
      progress: r.progress,
      submitted_at: r.submitted_at,
      submitter: r.submitter,
      version: r.version,
      is_latest: r.is_latest ?? true,
    }));
  }, [requests, isValidProp]);

  const [filters, setFilters] = useState<SearchFilterState>({ q: "", status: "all" });

  const filteredRows = useMemo(() => {
    const q = filters.q.trim().toLowerCase();
    const status = filters.status;
    return rows.filter((r) => {
      const matchesQ =
        !q ||
        r.form_name.toLowerCase().includes(q) ||
        r.form_code.toLowerCase().includes(q) ||
        r.status.toLowerCase().includes(q) ||
        r.submitter.toLowerCase().includes(q);
      const matchesStatus = status === "all" || r.status.toLowerCase() === status;
      return matchesQ && matchesStatus;
    });
  }, [rows, filters]);

  const filtersActive = filters.q.trim() !== "" || filters.status !== "all";

  return (
    <AppLayout title="All Requests" subtitle="A complete list of all requests assigned to you">
      <Head title="All Requests" />

      <div className="mx-auto w-full max-w-[1520px] space-y-4 sm:space-y-5 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
        <div className="motion-safe:animate-in motion-safe:fade-in">
          <Button
            asChild
            variant="ghost"
            className="group -ml-1 px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm text-muted-foreground hover:text-foreground hover:bg-muted motion-safe:transition-colors"
          >
            <Link href={route("staff-dashboard.index")} className="flex items-center gap-1.5 sm:gap-2">
              <svg className="w-3.5 h-3.5 sm:w-4 sm:h-4 motion-safe:transition-transform motion-safe:group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
              Back to Dashboard
            </Link>
          </Button>
        </div>

        <div className="motion-safe:animate-in motion-safe:fade-in">
          <SearchWithFilters
            value={filters}
            onChange={setFilters}
            showClear={false}
          />
        </div>

        <div className="motion-safe:animate-in motion-safe:fade-in">
          <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 className="text-sm font-semibold text-foreground">All Requests</h2>
              <p className="text-xs text-muted-foreground mt-0.5">Review and manage all requests assigned to you</p>
            </div>
            {filteredRows.length > 0 && (
              <p className="text-xs text-muted-foreground sm:text-right">
                <span className="font-semibold tabular-nums text-foreground">{filteredRows.length}</span>
                {filteredRows.length === 1 ? " request" : " requests"}
              </p>
            )}
          </div>
          {!isValidProp ? (
            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/60 px-6 py-12 text-center">
              <p className="text-sm font-semibold text-foreground">Unable to load requests</p>
              <p className="mt-1.5 max-w-sm text-xs text-muted-foreground">An unexpected response was received from the server. Please refresh the page.</p>
            </div>
          ) : (
            <RequestsCards
              requests={filteredRows}
              filtersActive={filtersActive}
            />
          )}
        </div>
      </div>
    </AppLayout>
  );
}
