import React, { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Card } from "@/components/ui/card";
import { Plus, Trash2 } from "lucide-react";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { format } from "date-fns";
import { cn } from "@/lib/utils";
import { CalendarIcon } from "lucide-react";

interface TableColumn {
  id: string;
  label: string;
  type: "text" | "number" | "date" | "textarea";
  required: boolean;
}

interface DynamicTableInputProps {
  columns: TableColumn[];
  value: Record<string, any>[];
  onChange: (value: Record<string, any>[]) => void;
  minRows?: number;
  maxRows?: number;
  disabled?: boolean;
  errors?: Record<string, string>;
}

export function DynamicTableInput({
  columns,
  value = [],
  onChange,
  minRows = 1,
  maxRows = 10,
  disabled = false,
  errors = {},
}: DynamicTableInputProps) {
  const [rows, setRows] = useState<Record<string, any>[]>(value);

  // Keep local rows in sync with external value updates
  useEffect(() => {
    setRows(Array.isArray(value) ? value : []);
  }, [value]);

  // Enforce min/max rows and align row shape to current columns
  useEffect(() => {
    const normalizedRows = (Array.isArray(rows) ? rows : []).map((row) => {
      const next: Record<string, any> = {};
      columns.forEach((col) => {
        next[col.id] = row?.[col.id] ?? "";
      });
      return next;
    });

    const boundedMax = Math.max(1, maxRows);
    const boundedMin = Math.min(Math.max(0, minRows), boundedMax);

    let nextRows = normalizedRows;

    if (nextRows.length > boundedMax) {
      nextRows = nextRows.slice(0, boundedMax);
    }

    if (nextRows.length < boundedMin) {
      const emptyRows = Array(boundedMin - nextRows.length)
        .fill(null)
        .map(() => {
          const row: Record<string, any> = {};
          columns.forEach((col) => (row[col.id] = ""));
          return row;
        });
      nextRows = [...nextRows, ...emptyRows];
    }

    const changed = JSON.stringify(nextRows) !== JSON.stringify(rows);
    if (changed) {
      setRows(nextRows);
      onChange(nextRows);
    }
  }, [columns, minRows, maxRows, rows, onChange]);

  const addRow = () => {
    if (rows.length >= maxRows) return;
    const newRow: Record<string, any> = {};
    columns.forEach((col) => (newRow[col.id] = ""));
    const newRows = [...rows, newRow];
    setRows(newRows);
    onChange(newRows);
  };

  const deleteRow = (index: number) => {
    if (rows.length <= minRows) return;
    const newRows = rows.filter((_, i) => i !== index);
    setRows(newRows);
    onChange(newRows);
  };

  const updateCell = (rowIndex: number, columnId: string, cellValue: any) => {
    const newRows = rows.map((row, i) =>
      i === rowIndex ? { ...row, [columnId]: cellValue } : row
    );
    setRows(newRows);
    onChange(newRows);
  };

  const renderCell = (column: TableColumn, rowIndex: number, value: any) => {
    const cellKey = `${column.id}_${rowIndex}`;
    const error = errors[cellKey];

    switch (column.type) {
      case "number":
        return (
          <Input
            type="number"
            value={value || ""}
            onChange={(e) => updateCell(rowIndex, column.id, e.target.value)}
            disabled={disabled}
            className={cn("h-9 text-sm", error && "border-red-500")}
            placeholder={column.required ? "Required" : "Optional"}
          />
        );

      case "date":
        return (
          <Popover>
            <PopoverTrigger asChild>
              <Button
                variant="outline"
                className={cn(
                  "h-9 w-full justify-start text-left font-normal text-sm",
                  !value && "text-muted-foreground",
                  error && "border-red-500"
                )}
                disabled={disabled}
              >
                <CalendarIcon className="mr-2 h-4 w-4" />
                {value ? format(new Date(value), "PPP") : <span>Pick a date</span>}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0">
              <Calendar
                mode="single"
                selected={value ? new Date(value) : undefined}
                onSelect={(date) =>
                  updateCell(rowIndex, column.id, date ? format(date, "yyyy-MM-dd") : "")
                }
                initialFocus
              />
            </PopoverContent>
          </Popover>
        );

      case "textarea":
        return (
          <Textarea
            value={value || ""}
            onChange={(e) => updateCell(rowIndex, column.id, e.target.value)}
            disabled={disabled}
            className={cn("text-sm min-h-[60px]", error && "border-red-500")}
            placeholder={column.required ? "Required" : "Optional"}
            rows={2}
          />
        );

      case "text":
      default:
        return (
          <Input
            type="text"
            value={value || ""}
            onChange={(e) => updateCell(rowIndex, column.id, e.target.value)}
            disabled={disabled}
            className={cn("h-9 text-sm", error && "border-red-500")}
            placeholder={column.required ? "Required" : "Optional"}
          />
        );
    }
  };

  if (columns.length === 0) {
    return (
      <Card className="p-4 text-center">
        <p className="text-sm text-muted-foreground">
          No columns configured for this table field.
        </p>
      </Card>
    );
  }

  // Mobile: Card view
  const mobileView = (
    <div className="block md:hidden space-y-3">
      {rows.map((row, rowIndex) => (
        <Card key={rowIndex} className="p-4 space-y-3">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-semibold text-muted-foreground">
              Row {rowIndex + 1}
            </span>
            {rows.length > minRows && (
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => deleteRow(rowIndex)}
                disabled={disabled}
                className="h-7 w-7 p-0"
              >
                <Trash2 className="w-3.5 h-3.5 text-destructive" />
              </Button>
            )}
          </div>
          {columns.map((column) => (
            <div key={column.id} className="space-y-1.5">
              <Label className="text-xs font-medium">
                {column.label}
                {column.required && <span className="text-red-500 ml-1">*</span>}
              </Label>
              {renderCell(column, rowIndex, row[column.id])}
            </div>
          ))}
        </Card>
      ))}
    </div>
  );

  // Desktop: Table view
  const desktopView = (
    <div className="hidden md:block overflow-x-auto">
      <table className="w-full border-collapse">
        <thead>
          <tr className="border-b bg-muted/50">
            <th className="w-12 p-2 text-left text-xs font-semibold text-muted-foreground">
              #
            </th>
            {columns.map((column) => (
              <th
                key={column.id}
                className="p-2 text-left text-xs font-semibold text-foreground"
              >
                {column.label}
                {column.required && <span className="text-red-500 ml-1">*</span>}
              </th>
            ))}
            <th className="w-12 p-2"></th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row, rowIndex) => (
            <tr key={rowIndex} className="border-b hover:bg-muted/30">
              <td className="p-2 text-xs text-muted-foreground">{rowIndex + 1}</td>
              {columns.map((column) => (
                <td key={column.id} className="p-2">
                  {renderCell(column, rowIndex, row[column.id])}
                </td>
              ))}
              <td className="p-2">
                {rows.length > minRows && (
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => deleteRow(rowIndex)}
                    disabled={disabled}
                    className="h-7 w-7 p-0"
                  >
                    <Trash2 className="w-3.5 h-3.5 text-destructive" />
                  </Button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  return (
    <div className="space-y-3">
      {mobileView}
      {desktopView}

      {/* Add Row Button */}
      {rows.length < maxRows && (
        <Button
          type="button"
          size="sm"
          variant="outline"
          onClick={addRow}
          disabled={disabled}
          className="w-full h-9 text-sm"
        >
          <Plus className="w-4 h-4 mr-2" />
          Add Row ({rows.length}/{maxRows})
        </Button>
      )}

      {/* Row count indicator */}
      <div className="text-xs text-muted-foreground text-center">
        {minRows > 0 && `Minimum ${minRows} row${minRows > 1 ? "s" : ""} required. `}
        {rows.length}/{maxRows} rows used
      </div>
    </div>
  );
}
