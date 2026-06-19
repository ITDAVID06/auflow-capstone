import React from "react";

interface PaginationProps {
  /** Current active page (1-indexed). */
  currentPage: number;
  /** Total number of pages. */
  lastPage: number;
  /** Visible item count on the current page. */
  currentCount: number;
  /** Total number of items across all pages. */
  total: number;
  /** Label for the items being paginated (e.g. "forms", "users"). */
  itemLabel?: string;
  /** Called when the user clicks a page button. */
  onPageChange: (page: number) => void;
  /** Whether to render pagination UI even when there is only one page. */
  alwaysShow?: boolean;
}

/**
 * Shared pagination component used system-wide.
 *
 * Displays "Showing X of Y {itemLabel}" on the left and numbered page
 * buttons (with First/Prev/Next/Last and ellipsis) on the right.
 */
export default function Pagination({
  currentPage,
  lastPage,
  currentCount,
  total,
  itemLabel = "items",
  onPageChange,
  alwaysShow = false,
}: PaginationProps) {
  const current = currentPage;
  const last = Math.max(1, lastPage);

  // Build page-number list with ellipsis
  const pages: (number | "…")[] = [];

  if (last <= 7) {
    for (let i = 1; i <= last; i++) pages.push(i);
  } else {
    pages.push(1);
    if (current > 4) pages.push("…");
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);
    for (let i = start; i <= end; i++) pages.push(i);
    if (current < last - 3) pages.push("…");
    pages.push(last);
  }

  if (!alwaysShow && last <= 1) return null;

  return (
    <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <span className="text-xs text-muted-foreground">
        Showing {currentCount} of {total} {itemLabel}
      </span>

      <div className="flex flex-wrap items-center gap-1">
        <PageBtn disabled={current === 1} onClick={() => onPageChange(1)}>
          « First
        </PageBtn>
        <PageBtn disabled={current === 1} onClick={() => onPageChange(current - 1)}>
          ‹ Prev
        </PageBtn>

        {pages.map((p, idx) =>
          p === "…" ? (
            <span key={`ellipsis-${idx}`} className="px-1.5 text-xs text-muted-foreground">
              …
            </span>
          ) : (
            <PageBtn key={p} active={p === current} onClick={() => onPageChange(p as number)}>
              {p}
            </PageBtn>
          ),
        )}

        <PageBtn disabled={current === last} onClick={() => onPageChange(current + 1)}>
          Next ›
        </PageBtn>
        <PageBtn disabled={current === last} onClick={() => onPageChange(last)}>
          Last »
        </PageBtn>
      </div>
    </div>
  );
}

function PageBtn({
  children,
  onClick,
  disabled,
  active,
}: {
  children: React.ReactNode;
  onClick: () => void;
  disabled?: boolean;
  active?: boolean;
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={[
        "h-8 min-w-8 rounded-md px-2 text-xs font-medium transition-all",
        active
          ? "bg-primary text-primary-foreground shadow-sm"
          : "text-muted-foreground hover:bg-accent hover:text-foreground",
        disabled ? "pointer-events-none opacity-40" : "",
      ].join(" ")}
    >
      {children}
    </button>
  );
}
