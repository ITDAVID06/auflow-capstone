import * as React from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { RefreshCw, Plus } from "lucide-react";

type PageToolbarProps = {
  searchValue: string;
  onSearchChange: (v: string) => void;
  onRefresh?: () => void;
  onCreate?: () => void;
  createLabel?: string;
  placeholder?: string;
  disabled?: boolean;
  extraLeft?: React.ReactNode;
  extraRight?: React.ReactNode;
};

export default function PageToolbar({
  searchValue,
  onSearchChange,
  onRefresh,
  onCreate,
  createLabel = "Create New",
  placeholder = "Search…",
  disabled = false,
  extraLeft,
  extraRight,
}: PageToolbarProps) {
  return (
    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-3">
      <div className="flex items-center gap-2">
        <div className="w-72">
            <Input
            value={searchValue}
            onChange={(e) => onSearchChange(e.target.value)}
            placeholder={placeholder}
            className="h-9"
            />
        </div>
        {extraLeft}
        </div>


      <div className="flex items-center gap-2">
        {onRefresh && (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onRefresh}
            disabled={disabled}
            className="h-9"
          >
            <RefreshCw className="mr-2 h-4 w-4" />
            Refresh
          </Button>
        )}
        {extraRight}
        {onCreate && (
          <Button type="button" size="sm" onClick={onCreate} className="h-9">
            <Plus className="mr-2 h-4 w-4" />
            {createLabel}
          </Button>
        )}
      </div>
    </div>
  );
}
