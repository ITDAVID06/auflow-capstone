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
            filterableColumns={capabilities.filterable_columns}
            operatorsByColumn={capabilities.operators_by_column}
            isLoading={false}
            onFiltersChange={onChange}
          />
        </div>
      )}
    </div>
  );
};
