import React from "react";
import Pagination from "@/components/shared/Pagination";

interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
  total: number;
}

interface WorkflowPaginationProps {
  pagination: Paginated<unknown>;
  currentItemsCount: number;
  onPageChange: (page: number) => void;
}

export default function WorkflowPagination({
  pagination,
  currentItemsCount,
  onPageChange,
}: WorkflowPaginationProps) {
  return (
    <Pagination
      currentPage={pagination.current_page ?? 1}
      lastPage={pagination.last_page ?? 1}
      currentCount={currentItemsCount}
      total={pagination.total}
      itemLabel="workflows"
      onPageChange={onPageChange}
      alwaysShow
    />
  );
}
