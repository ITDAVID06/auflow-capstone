import React from "react";
import Pagination from "@/components/shared/Pagination";
import { PaginatedForms } from "../types/form.types";

interface FormPaginationProps {
  forms: PaginatedForms;
  onPageChange: (page: number) => void;
}

export default function FormPagination({ forms, onPageChange }: FormPaginationProps) {
  return (
    <Pagination
      currentPage={forms.current_page ?? 1}
      lastPage={forms.last_page ?? 1}
      currentCount={forms.data.length}
      total={forms.total}
      itemLabel="forms"
      onPageChange={onPageChange}
    />
  );
}
