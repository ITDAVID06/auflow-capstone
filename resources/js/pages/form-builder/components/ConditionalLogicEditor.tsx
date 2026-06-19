import React from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Plus, X, Zap } from "lucide-react";
import type { FormField, FieldCondition } from "@/types/form";
import { isNonInputFieldType } from "../config/fieldTypeRegistry";

interface ConditionalLogicEditorProps {
  field: FormField;
  allFields: FormField[];
  onChange: (changes: Partial<FormField>) => void;
  locked?: boolean;
}

const OPERATORS: { value: FieldCondition["operator"]; label: string }[] = [
  { value: "equals", label: "Equals" },
  { value: "not_equals", label: "Does not equal" },
  { value: "contains", label: "Contains" },
  { value: "not_empty", label: "Is not empty" },
  { value: "is_empty", label: "Is empty" },
];

export function ConditionalLogicEditor({
  field,
  allFields,
  onChange,
  locked = false,
}: ConditionalLogicEditorProps) {
  const targets = allFields.filter(
    (f) =>
      String(f.id) !== String(field.id) &&
      !isNonInputFieldType(f.data_type)
  );

  if (targets.length === 0) return null;

  const conditions: FieldCondition[] = field.conditions ?? [];

  const add = () => {
    const c: FieldCondition = {
      field_name: targets[0].field_name,
      operator: "not_empty",
      value: "",
      action: "show",
    };
    onChange({ conditions: [...conditions, c] });
  };

  const update = (idx: number, patch: Partial<FieldCondition>) => {
    onChange({
      conditions: conditions.map((c, i) => (i === idx ? { ...c, ...patch } : c)),
    });
  };

  const remove = (idx: number) => {
    onChange({ conditions: conditions.filter((_, i) => i !== idx) });
  };

  return (
    <div className="mt-3 space-y-2 border-t pt-3">
      <div className="flex items-center justify-between">
        <Label className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
          <Zap className="w-3 h-3" />
          Conditional Logic
        </Label>
        <Button
          variant="ghost"
          size="sm"
          className="h-6 text-xs"
          onClick={add}
          disabled={locked}
        >
          <Plus className="w-3 h-3 mr-1" />
          Add Rule
        </Button>
      </div>

      {conditions.map((cond, idx) => (
        <div key={idx} className="flex flex-wrap items-center gap-1.5 p-2 rounded-md border bg-muted/20">
          {/* Action */}
          <Select
            value={cond.action}
            onValueChange={(v) => update(idx, { action: v as "show" | "hide" })}
            disabled={locked}
          >
            <SelectTrigger className="h-6 text-xs w-[80px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="show">Show</SelectItem>
              <SelectItem value="hide">Hide</SelectItem>
            </SelectContent>
          </Select>

          <span className="text-xs text-muted-foreground">when</span>

          {/* Target field */}
          <Select
            value={cond.field_name}
            onValueChange={(v) => update(idx, { field_name: v })}
            disabled={locked}
          >
            <SelectTrigger className="h-6 text-xs w-[120px]">
              <SelectValue placeholder="Field" />
            </SelectTrigger>
            <SelectContent>
              {targets.map((t) => (
                <SelectItem key={t.field_name} value={t.field_name}>
                  {t.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {/* Operator */}
          <Select
            value={cond.operator}
            onValueChange={(v) => update(idx, { operator: v as FieldCondition["operator"] })}
            disabled={locked}
          >
            <SelectTrigger className="h-6 text-xs w-[120px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {OPERATORS.map((op) => (
                <SelectItem key={op.value} value={op.value}>
                  {op.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {/* Value input */}
          {!["not_empty", "is_empty"].includes(cond.operator) && (
            <Input
              value={String(cond.value ?? "")}
              onChange={(e) => update(idx, { value: e.target.value })}
              placeholder="Value"
              disabled={locked}
              className="h-6 text-xs w-[100px]"
            />
          )}

          <Button
            variant="ghost"
            size="icon"
            className="h-5 w-5 shrink-0"
            onClick={() => remove(idx)}
            disabled={locked}
          >
            <X className="w-3 h-3" />
          </Button>
        </div>
      ))}
    </div>
  );
}
