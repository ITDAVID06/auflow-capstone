import React from "react";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

import type {
  FormField,
  SingleMetaSelection,
} from "@/types/form";
import { normalizeMetaOption } from "../../utils/meta";

interface MetaSelectFieldProps {
  field: FormField;
  value: SingleMetaSelection | undefined;
  onSelect: (field: FormField, value: string) => void;
}

export const MetaSelectField: React.FC<MetaSelectFieldProps> = ({
  field,
  value,
  onSelect,
}) => {
  const normalizedOptions = (field.options_meta || []).map(normalizeMetaOption);

  return (
    <div className="space-y-2">
      <Label className="block">
        {field.label}
        {field.is_required && <span className="ml-1 text-red-500">*</span>}
      </Label>
      <Select
        value={value?.value ?? ""}
        onValueChange={(val) => onSelect(field, val)}
        required
      >
        <SelectTrigger className="w-full">
          <SelectValue placeholder="Select…" />
        </SelectTrigger>
        <SelectContent>
          {normalizedOptions.map((option) => (
            <SelectItem key={option.value} value={String(option.value)}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {field.help_text && (
        <p className="text-xs text-gray-500 dark:text-gray-400">{field.help_text}</p>
      )}
    </div>
  );
};
