import React from "react";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import type {
  FormField,
  OptionMeta,
  SingleMetaSelection,
} from "@/types/form";
import { normalizeMetaOption } from "../../utils/meta";

interface MetaRadioGroupProps {
  field: FormField;
  value: SingleMetaSelection | undefined;
  onSelect: (field: FormField, value: string) => void;
  onQtyChange: (field: FormField, rawValue: string) => void;
  onTextChange: (field: FormField, text: string) => void;
}

export const MetaRadioGroup: React.FC<MetaRadioGroupProps> = ({
  field,
  value,
  onSelect,
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
        {normalizedOptions.map((option) => (
          <div key={option.value} className="rounded-md border border-gray-200 dark:border-gray-700 p-3">
            <label className="flex items-center gap-2 text-sm font-medium">
              <input
                type="radio"
                name={String(field.field_name)}
                checked={value?.value === option.value}
                onChange={() => onSelect(field, option.value)}
              />
              <span>{option.label}</span>
            </label>

            {option.requires_qty && value?.value === option.value && (
              <div className="mt-2 flex items-center gap-2 pl-6">
                <span className="text-xs text-gray-500 dark:text-gray-400">{option.qty_label || "Qty"}</span>
                <Input
                  type="number"
                  className="w-24"
                  value={value?.qty ?? ""}
                  min={option.min_qty ?? 0}
                  max={option.max_qty ?? undefined}
                  step={option.step ?? 1}
                  onChange={(event) => onQtyChange(field, event.target.value)}
                  placeholder={String(option.default_qty ?? 1)}
                />
                {option.unit && <span className="text-xs text-gray-500 dark:text-gray-400">{option.unit}</span>}
              </div>
            )}

            {option.requires_text && value?.value === option.value && (
              <div className="mt-2 flex items-center gap-2 pl-6">
                <span className="text-xs text-gray-500 dark:text-gray-400">{option.text_label || "Specify"}</span>
                <Input
                  type="text"
                  className="w-[260px]"
                  value={value?.text ?? ""}
                  onChange={(event) => onTextChange(field, event.target.value)}
                />
              </div>
            )}
          </div>
        ))}
      </div>
      {field.help_text && (
        <p className="text-xs text-gray-500 dark:text-gray-400">{field.help_text}</p>
      )}
    </div>
  );
};
