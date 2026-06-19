import React from "react";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import type {
  FormField,
  MultiMetaSelection,
  OptionMeta,
} from "@/types/form";
import { normalizeMetaOption } from "../../utils/meta";

interface MetaCheckboxGroupProps {
  field: FormField;
  value: MultiMetaSelection;
  onToggle: (field: FormField, option: OptionMeta, checked: boolean) => void;
  onQtyChange: (field: FormField, option: OptionMeta, value: string) => void;
  onTextChange: (field: FormField, option: OptionMeta, value: string) => void;
}

export const MetaCheckboxGroup: React.FC<MetaCheckboxGroupProps> = ({
  field,
  value,
  onToggle,
  onQtyChange,
  onTextChange,
}) => {
  const normalizedOptions = (field.options_meta || []).map(normalizeMetaOption);

  return (
    <div className="space-y-2">
      <Label className="block">
        {field.label}
        {field.is_required && <span className="ml-1 text-red-500">*</span>}
      </Label>
      <div className="flex flex-col gap-2">
        {normalizedOptions.map((option) => {
          const checked = value.some((item) => item.value === option.value);
          const current = value.find((item) => item.value === option.value);

          return (
            <div key={option.value} className="rounded-md border border-gray-200 dark:border-gray-700 p-3">
              <label className="flex items-center gap-2 text-sm font-medium">
                <input
                  type="checkbox"
                  checked={checked}
                  onChange={(event) => onToggle(field, option, event.target.checked)}
                />
                <span>{option.label}</span>
              </label>

              {option.requires_qty && checked && (
                <div className="mt-2 flex items-center gap-2 pl-6">
                  <span className="text-xs text-gray-500 dark:text-gray-400">{option.qty_label || "Qty"}</span>
                  <Input
                    type="number"
                    className="w-24"
                    value={current?.qty ?? ""}
                    min={option.min_qty ?? 0}
                    max={option.max_qty ?? undefined}
                    step={option.step ?? 1}
                    onChange={(event) => onQtyChange(field, option, event.target.value)}
                    placeholder={String(option.default_qty ?? 1)}
                  />
                  {option.unit && <span className="text-xs text-gray-500 dark:text-gray-400">{option.unit}</span>}
                </div>
              )}

              {option.requires_text && checked && (
                <div className="mt-2 flex items-center gap-2 pl-6">
                  <span className="text-xs text-gray-500 dark:text-gray-400">{option.text_label || "Specify"}</span>
                  <Input
                    type="text"
                    className="w-[260px]"
                    value={current?.text ?? ""}
                    onChange={(event) => onTextChange(field, option, event.target.value)}
                  />
                </div>
              )}
            </div>
          );
        })}
      </div>
      {field.help_text && (
        <p className="text-xs text-gray-500 dark:text-gray-400">{field.help_text}</p>
      )}
    </div>
  );
};
