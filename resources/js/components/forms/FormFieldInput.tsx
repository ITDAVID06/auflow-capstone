import React from "react";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Select, SelectTrigger, SelectValue, SelectItem, SelectContent } from "@/components/ui/select";

type FormField = {
  id: number;
  field_name: string;
  label: string;
  data_type: string;
  is_required: boolean;
  options: string[] | null;
  field_order: number;
};

interface Props {
  field: FormField;
  value: any;
  onChange: (value: any) => void;
  error?: string;
}

export function FormFieldInput({ field, value, onChange, error }: Props) {
  const fieldId = `field-${field.field_name}`;
  const options = Array.isArray(field.options) ? field.options : [];

  const label = (
    <Label htmlFor={fieldId} className="block mb-1 text-sm font-medium">
      {field.label}
      {field.is_required && <span className="text-red-600 ml-1">*</span>}
    </Label>
  );

  const errorEl = error ? (
    <p id={`${fieldId}-error`} className="mt-1 text-xs text-red-600" role="alert">
      {error}
    </p>
  ) : null;

  switch (field.data_type) {
    case "text":
    case "email":
    case "number":
    case "date":
    case "phone":
      return (
        <div>
          {label}
          <Input
            id={fieldId}
            type={field.data_type === "phone" ? "tel" : field.data_type}
            value={value}
            onChange={(e: React.ChangeEvent<HTMLInputElement>) => onChange(e.target.value)}
            required={field.is_required}
            aria-describedby={error ? `${fieldId}-error` : undefined}
            aria-invalid={error ? true : undefined}
          />
          {errorEl}
        </div>
      );

    case "textarea":
      return (
        <div>
          {label}
          <Textarea
            id={fieldId}
            value={value}
            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => onChange(e.target.value)}
            required={field.is_required}
            aria-describedby={error ? `${fieldId}-error` : undefined}
            aria-invalid={error ? true : undefined}
          />
          {errorEl}
        </div>
      );

    case "select":
      return (
        <div>
          {label}
          <Select onValueChange={(val: string) => onChange(val)} value={value} required={field.is_required}>
            <SelectTrigger
              id={fieldId}
              aria-describedby={error ? `${fieldId}-error` : undefined}
              aria-invalid={error ? true : undefined}
            >
              <SelectValue placeholder="Select an option" />
            </SelectTrigger>
            <SelectContent>
              {options.map((opt, idx) => (
                <SelectItem key={idx} value={opt}>
                  {opt}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errorEl}
        </div>
      );

    case "radio":
      return (
        <div>
          <p id={`${fieldId}-label`} className="block mb-1 text-sm font-medium">
            {field.label}
            {field.is_required && <span className="text-red-600 ml-1">*</span>}
          </p>
          <RadioGroup
            value={value}
            onValueChange={(val: string) => onChange(val)}
            className="flex gap-4"
            aria-labelledby={`${fieldId}-label`}
            aria-describedby={error ? `${fieldId}-error` : undefined}
            aria-invalid={error ? true : undefined}
          >
            {options.map((opt, idx) => (
              <div key={idx} className="flex items-center gap-1">
                <RadioGroupItem value={opt} id={`${field.field_name}-${idx}`} />
                <Label htmlFor={`${field.field_name}-${idx}`}>{opt}</Label>
              </div>
            ))}
          </RadioGroup>
          {errorEl}
        </div>
      );

    case "checkbox":
      return options.length > 0 ? (
        <div>
          <p id={`${fieldId}-label`} className="block mb-1 text-sm font-medium">
            {field.label}
            {field.is_required && <span className="text-red-600 ml-1">*</span>}
          </p>
          <div
            className="flex flex-col gap-1"
            role="group"
            aria-labelledby={`${fieldId}-label`}
            aria-describedby={error ? `${fieldId}-error` : undefined}
          >
            {options.map((opt, idx) => {
              const checked = Array.isArray(value) ? value.includes(opt) : false;
              const optId = `${field.field_name}-opt-${idx}`;
              return (
                <div key={idx} className="flex items-center gap-2">
                  <Checkbox
                    id={optId}
                    checked={checked}
                    onCheckedChange={(checked: boolean) => {
                      const newVal = new Set(Array.isArray(value) ? value : []);
                      if (checked) {
                        newVal.add(opt);
                      } else {
                        newVal.delete(opt);
                      }
                      onChange(Array.from(newVal));
                    }}
                  />
                  <Label htmlFor={optId}>{opt}</Label>
                </div>
              );
            })}
          </div>
          {errorEl}
        </div>
      ) : (
        <div className="flex items-center gap-2">
          <Checkbox
            id={fieldId}
            checked={!!value}
            onCheckedChange={(checked: boolean) => onChange(checked)}
          />
          <Label htmlFor={fieldId} className="text-sm font-medium">
            {field.label}
            {field.is_required && <span className="text-red-600 ml-1">*</span>}
          </Label>
          {errorEl}
        </div>
      );

    case "file":
      return (
        <div>
          {label}
          <Input
            id={fieldId}
            type="file"
            onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
              onChange(e.target.files?.[0] ?? null)
            }
            required={field.is_required}
            aria-describedby={error ? `${fieldId}-error` : undefined}
            aria-invalid={error ? true : undefined}
          />
          {errorEl}
        </div>
      );

    default:
      return (
        <div>
          {label}
          <Input
            id={fieldId}
            type="text"
            value={value}
            onChange={(e: React.ChangeEvent<HTMLInputElement>) => onChange(e.target.value)}
            required={field.is_required}
            aria-describedby={error ? `${fieldId}-error` : undefined}
            aria-invalid={error ? true : undefined}
          />
          {errorEl}
        </div>
      );
  }
}
