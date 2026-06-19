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
