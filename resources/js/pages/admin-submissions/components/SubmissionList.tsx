import React, { useState, useMemo, useEffect } from "react";
import Pagination from "@/components/shared/Pagination";
import CompactSubmissionRow from "./CompactSubmissionRow";
import type { Row } from "./AdminRequestCards";

interface SubmissionListProps {
  submissions: Row[];
}

export default function SubmissionList({ submissions }: SubmissionListProps) {
  const [page, setPage] = useState(1);
  const perPage = 10;

  const total = submissions.length;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  useEffect(() => {
    if (page > lastPage) setPage(lastPage);
  }, [lastPage, page]);

  const start = (page - 1) * perPage;
  const end = Math.min(total, start + perPage);
  const currentPage = useMemo(
    () => submissions.slice(start, end),
    [submissions, start, end]
  );

  return (
    <div className="w-full bg-white dark:bg-background">
      <div className="mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8 py-6">
        <div className="space-y-4">
          {/* Submission Rows */}
          {currentPage.length === 0 ? (
            <div className="text-center py-12">
              <p className="text-muted-foreground text-lg">No submissions found.</p>
            </div>
          ) : (
            currentPage.map((submission) => (
              <CompactSubmissionRow key={submission.id} submission={submission} />
            ))
          )}

          {/* Pagination */}
          <Pagination
            currentPage={page}
            lastPage={lastPage}
            currentCount={currentPage.length}
            total={total}
            itemLabel="submissions"
            onPageChange={setPage}
          />
        </div>
      </div>
    </div>
  );
}
