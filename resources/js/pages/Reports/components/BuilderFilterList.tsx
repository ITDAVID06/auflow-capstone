import React from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { LayersIcon, Plus, X } from "lucide-react";
import {
  ReportColumn,
  ReportFilterClause,
  ReportFilterGroup,
  ReportFilterItem,
  ReportFilterOperator,
  isFilterGroup,
} from "../types";
import DatePickerInput from "./DatePickerInput";

const DATE_LIKE_TYPES = ["date", "datetime", "datetime-local", "timestamp"];

const OPERATOR_LABELS: Record<ReportFilterOperator, string> = {
  eq: "Equals",
  neq: "Not equals",
  contains: "Contains",
  starts_with: "Starts with",
  ends_with: "Ends with",
  gt: "Greater than",
  gte: "Greater or equal",
  lt: "Less than",
  lte: "Less or equal",
  is_null: "Is empty",
  is_not_null: "Is not empty",
  in: "Is any of",
  between: "Between",
};

const DEFAULT_OPERATOR: ReportFilterOperator = "eq";

const COLUMN_ENUM_OPTIONS: Record<string, { value: string; label: string }[]> = {
  submission_status: [
    { value: "Pending", label: "Pending" },
    { value: "Completed", label: "Approved" },
    { value: "Rejected", label: "Rejected" },
  ],
  workflow_status: [
    { value: "Pending", label: "Pending" },
    { value: "Approved", label: "Approved" },
    { value: "Rejected", label: "Rejected" },
  ],
};

const shouldRenderEnumDropdown = (column: string, operator: ReportFilterOperator): boolean => {
  if (!COLUMN_ENUM_OPTIONS[column]) return false;
  return !["in", "between", "is_null", "is_not_null"].includes(operator);
};

// ─── Leaf filter row ─────────────────────────────────────────────────────────

interface LeafFilterRowProps {
  filter: ReportFilterClause;
  index: number;
  filterableColumns: ReportColumn[];
  operatorsByColumn: Record<string, ReportFilterOperator[]>;
  isLoading: boolean;
  onUpdate: (patch: Partial<ReportFilterClause>) => void;
  onRemove: () => void;
  logicLabel?: string;
}

function LeafFilterRow({
  filter,
  filterableColumns,
  operatorsByColumn,
  isLoading,
  onUpdate,
  onRemove,
  logicLabel,
}: LeafFilterRowProps) {
  const operatorsForColumn = (column: string): ReportFilterOperator[] => {
    const ops = operatorsByColumn[column];
    if (Array.isArray(ops) && ops.length > 0) return ops;
    return [DEFAULT_OPERATOR];
  };

  const isDateColumn = DATE_LIKE_TYPES.includes(
    filterableColumns.find((c) => c.key === filter.column)?.type?.toLowerCase() ?? "",
  );

  return (
    <div className="grid grid-cols-1 gap-2 md:grid-cols-12">
      {logicLabel ? (
        <div className="flex items-center md:col-span-1">
          <span className="w-full rounded bg-muted px-1.5 py-0.5 text-center text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
            {logicLabel}
          </span>
        </div>
      ) : null}

      <div className={logicLabel ? "md:col-span-3" : "md:col-span-4"}>
        <Select
          value={filter.column}
          onValueChange={(value) => {
            const [firstOperator] = operatorsForColumn(value);
            const nextOperator = operatorsForColumn(value).includes(filter.operator)
              ? filter.operator
              : (firstOperator ?? DEFAULT_OPERATOR);

            let nextValue: string | string[] | null;
            if (nextOperator === "is_null" || nextOperator === "is_not_null") {
              nextValue = null;
            } else if (nextOperator === "between") {
              nextValue = ["", ""];
            } else {
              const prevVal = filter.value;
              nextValue = Array.isArray(prevVal) ? (prevVal[0] ?? "") : (prevVal ?? "");
            }

            onUpdate({ column: value, operator: nextOperator, value: nextValue });
          }}
          disabled={isLoading}
        >
          <SelectTrigger>
            <SelectValue placeholder="Column" />
          </SelectTrigger>
          <SelectContent>
            {filterableColumns.map((column) => (
              <SelectItem key={column.key} value={column.key}>
                {column.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="md:col-span-3">
        <Select
          value={filter.operator}
          onValueChange={(value) => {
            const nextOperator = value as ReportFilterOperator;
            const prevOperator = filter.operator;
            let nextValue: string | string[] | null = filter.value;

            if (nextOperator === "is_null" || nextOperator === "is_not_null") {
              nextValue = null;
            } else if (nextOperator === "between" && prevOperator !== "between") {
              nextValue = ["", ""];
            } else if (prevOperator === "between" && nextOperator !== "between") {
              const parts = Array.isArray(filter.value) ? filter.value : [];
              nextValue = parts[0] ?? "";
            }

            onUpdate({ operator: nextOperator, value: nextValue });
          }}
          disabled={isLoading}
        >
          <SelectTrigger>
            <SelectValue placeholder="Operator" />
          </SelectTrigger>
          <SelectContent>
            {operatorsForColumn(filter.column).map((op) => (
              <SelectItem key={op} value={op}>
                {OPERATOR_LABELS[op]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="md:col-span-4">
        {filter.operator === "between" ? (
          <div className="flex gap-1">
            {isDateColumn ? (
              <>
                <DatePickerInput
                  value={Array.isArray(filter.value) ? (filter.value[0] ?? "") : ""}
                  onChange={(v) => {
                    const parts = Array.isArray(filter.value) ? [...filter.value] : ["", ""];
                    parts[0] = v;
                    onUpdate({ value: parts });
                  }}
                  disabled={isLoading}
                  placeholder="From"
                />
                <DatePickerInput
                  value={Array.isArray(filter.value) ? (filter.value[1] ?? "") : ""}
                  onChange={(v) => {
                    const parts = Array.isArray(filter.value) ? [...filter.value] : ["", ""];
                    parts[1] = v;
                    onUpdate({ value: parts });
                  }}
                  disabled={isLoading}
                  placeholder="To"
                />
              </>
            ) : (
              <>
                <Input
                  type="text"
                  value={Array.isArray(filter.value) ? (filter.value[0] ?? "") : ""}
                  onChange={(event) => {
                    const parts = Array.isArray(filter.value) ? [...filter.value] : ["", ""];
                    parts[0] = event.target.value;
                    onUpdate({ value: parts });
                  }}
                  placeholder="From"
                  disabled={isLoading}
                />
                <Input
                  type="text"
                  value={Array.isArray(filter.value) ? (filter.value[1] ?? "") : ""}
                  onChange={(event) => {
                    const parts = Array.isArray(filter.value) ? [...filter.value] : ["", ""];
                    parts[1] = event.target.value;
                    onUpdate({ value: parts });
                  }}
                  placeholder="To"
                  disabled={isLoading}
                />
              </>
            )}
          </div>
        ) : shouldRenderEnumDropdown(filter.column, filter.operator) ? (
          <Select
            value={typeof filter.value === "string" ? filter.value : ""}
            onValueChange={(v) => onUpdate({ value: v })}
            disabled={isLoading}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select value" />
            </SelectTrigger>
            <SelectContent>
              {COLUMN_ENUM_OPTIONS[filter.column]?.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : (
          isDateColumn && filter.operator !== "is_null" && filter.operator !== "is_not_null" && filter.operator !== "in" ? (
            <DatePickerInput
              value={typeof filter.value === "string" ? filter.value : ""}
              onChange={(v) => onUpdate({ value: v })}
              disabled={isLoading}
            />
          ) : (
            <Input
              type="text"
              value={Array.isArray(filter.value) ? filter.value.join(", ") : (filter.value ?? "")}
              onChange={(event) => onUpdate({ value: event.target.value })}
              placeholder={filter.operator === "in" ? "value1, value2, ..." : "Value"}
              disabled={
                isLoading ||
                filter.operator === "is_null" ||
                filter.operator === "is_not_null"
              }
            />
          )
        )}
      </div>

      <div className="md:col-span-1">
        <Button
          type="button"
          variant="ghost"
          size="icon"
          onClick={onRemove}
          disabled={isLoading}
          aria-label="Remove filter"
        >
          <X className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}

// ─── Filter group block ───────────────────────────────────────────────────────

interface FilterGroupBlockProps {
  group: ReportFilterGroup;
  groupIndex: number;
  filterableColumns: ReportColumn[];
  operatorsByColumn: Record<string, ReportFilterOperator[]>;
  isLoading: boolean;
  onGroupChange: (updated: ReportFilterGroup) => void;
  onRemoveGroup: () => void;
}

function FilterGroupBlock({
  group,
  filterableColumns,
  operatorsByColumn,
  isLoading,
  onGroupChange,
  onRemoveGroup,
}: FilterGroupBlockProps) {
  const operatorsForColumn = (column: string): ReportFilterOperator[] => {
    const ops = operatorsByColumn[column];
    if (Array.isArray(ops) && ops.length > 0) return ops;
    return [DEFAULT_OPERATOR];
  };

  const addLeaf = () => {
    const firstColumn = filterableColumns[0]?.key;
    if (!firstColumn) return;
    const [firstOperator] = operatorsForColumn(firstColumn);
    onGroupChange({
      ...group,
      filters: [
        ...group.filters,
        { column: firstColumn, operator: firstOperator ?? DEFAULT_OPERATOR, value: "" },
      ],
    });
  };

  const updateLeaf = (index: number, patch: Partial<ReportFilterClause>) => {
    onGroupChange({
      ...group,
      filters: group.filters.map((f, i) => (i === index ? { ...f, ...patch } : f)),
    });
  };

  const removeLeaf = (index: number) => {
    onGroupChange({ ...group, filters: group.filters.filter((_, i) => i !== index) });
  };

  return (
    <div className="rounded-md border border-primary/30 bg-primary/5 p-3">
      <div className="mb-2 flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <LayersIcon className="h-3.5 w-3.5 text-primary" />
          <span className="text-xs font-semibold text-primary">Filter Group</span>
          <Select
            value={group.logic}
            onValueChange={(value) =>
              onGroupChange({ ...group, logic: value as "and" | "or" })
            }
            disabled={isLoading}
          >
            <SelectTrigger className="h-7 w-20 text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="and">AND</SelectItem>
              <SelectItem value="or">OR</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="flex gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-7 text-xs"
            onClick={addLeaf}
            disabled={isLoading}
          >
            <Plus className="mr-1 h-3 w-3" />
            Add Filter
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="h-7 w-7 text-destructive hover:text-destructive"
            onClick={onRemoveGroup}
            disabled={isLoading}
            aria-label="Remove filter group"
          >
            <X className="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>

      <div className="space-y-2">
        {group.filters.length === 0 ? (
          <p className="py-2 text-center text-xs text-muted-foreground">
            No filters in group — click <strong>Add Filter</strong>.
          </p>
        ) : (
          group.filters.map((leaf, leafIndex) => (
            <LeafFilterRow
              key={`group-leaf-${leafIndex}`}
              filter={leaf}
              index={leafIndex}
              filterableColumns={filterableColumns}
              operatorsByColumn={operatorsByColumn}
              isLoading={isLoading}
              logicLabel={leafIndex === 0 ? undefined : group.logic.toUpperCase()}
              onUpdate={(patch) => updateLeaf(leafIndex, patch)}
              onRemove={() => removeLeaf(leafIndex)}
            />
          ))
        )}
      </div>
    </div>
  );
}

// ─── Main component ───────────────────────────────────────────────────────────

interface BuilderFilterListProps {
  filters: ReportFilterItem[];
  filterableColumns: ReportColumn[];
  operatorsByColumn: Record<string, ReportFilterOperator[]>;
  isLoading: boolean;
  onFiltersChange: (filters: ReportFilterItem[]) => void;
}

export default function BuilderFilterList({
  filters,
  filterableColumns,
  operatorsByColumn,
  isLoading,
  onFiltersChange,
}: BuilderFilterListProps) {
  const operatorsForColumn = (column: string): ReportFilterOperator[] => {
    const ops = operatorsByColumn[column];
    if (Array.isArray(ops) && ops.length > 0) return ops;
    return [DEFAULT_OPERATOR];
  };

  const addLeaf = () => {
    const firstColumn = filterableColumns[0]?.key;
    if (!firstColumn) return;
    const [firstOperator] = operatorsForColumn(firstColumn);
    onFiltersChange([
      ...filters,
      { column: firstColumn, operator: firstOperator ?? DEFAULT_OPERATOR, value: "" },
    ]);
  };

  const addGroup = () => {
    const firstColumn = filterableColumns[0]?.key;
    if (!firstColumn) return;
    const [firstOperator] = operatorsForColumn(firstColumn);
    const group: ReportFilterGroup = {
      logic: "or",
      filters: [
        { column: firstColumn, operator: firstOperator ?? DEFAULT_OPERATOR, value: "" },
      ],
    };
    onFiltersChange([...filters, group]);
  };

  const updateItem = (index: number, updated: ReportFilterItem) => {
    onFiltersChange(filters.map((item, i) => (i === index ? updated : item)));
  };

  const removeItem = (index: number) => {
    onFiltersChange(filters.filter((_, i) => i !== index));
  };

  const totalCount = filters.length;

  return (
    <div className="rounded-lg border border-border/60 p-3">
      <div className="mb-3 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Builder filters
          </p>
          {totalCount > 0 && (
            <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-medium text-primary-foreground">
              {totalCount}
            </span>
          )}
        </div>
        <div className="flex gap-2">
          <Button type="button" variant="outline" size="sm" onClick={addGroup} disabled={isLoading}>
            <LayersIcon className="mr-1 h-3 w-3" />
            Add Group
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={addLeaf} disabled={isLoading}>
            <Plus className="mr-1 h-3 w-3" />
            Add Filter
          </Button>
        </div>
      </div>

      <div className="space-y-2">
        {filters.length === 0 ? (
          <p className="py-2 text-center text-sm text-muted-foreground">
            No advanced filters — click <strong>Add Filter</strong> or <strong>Add Group</strong>.
          </p>
        ) : (
          filters.map((item, index) => {
            if (isFilterGroup(item)) {
              return (
                <FilterGroupBlock
                  key={`group-${index}`}
                  group={item}
                  groupIndex={index}
                  filterableColumns={filterableColumns}
                  operatorsByColumn={operatorsByColumn}
                  isLoading={isLoading}
                  onGroupChange={(updated) => updateItem(index, updated)}
                  onRemoveGroup={() => removeItem(index)}
                />
              );
            }

            return (
              <LeafFilterRow
                key={`leaf-${index}`}
                filter={item}
                index={index}
                filterableColumns={filterableColumns}
                operatorsByColumn={operatorsByColumn}
                isLoading={isLoading}
                onUpdate={(patch) => updateItem(index, { ...item, ...patch })}
                onRemove={() => removeItem(index)}
              />
            );
          })
        )}
      </div>
    </div>
  );
}
