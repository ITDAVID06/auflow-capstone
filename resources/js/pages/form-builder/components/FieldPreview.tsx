import React from "react";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { FormField } from "../types/formBuilderTypes";

export function FieldPreview({ field }: { field: FormField }) {
  switch (field.data_type) {
    case "text":
      return (
        <div className="mb-4">
          <label className="block mb-1">
            {field.label}
            {field.is_required && <span className="text-red-500 ml-1">*</span>}
          </label>
          <Input placeholder={field.label} disabled />
        </div>
      );
    case "textarea":
      return (
        <div className="mb-4">
          <label className="block mb-1">
            {field.label}
            {field.is_required && <span className="text-red-500 ml-1">*</span>}
          </label>
          <Textarea placeholder={field.label} disabled />
        </div>
      );
    case "number":
      return (
        <div className="mb-4">
          <label className="block mb-1">
            {field.label}
            {field.is_required && <span className="text-red-500 ml-1">*</span>}
          </label>
          <Input type="number" placeholder={field.label} disabled />
        </div>
      );
    case "date":
      return (
        <div className="mb-4">
          <label className="block mb-1">
            {field.label}
            {field.is_required && <span className="text-red-500 ml-1">*</span>}
          </label>
          <Input type="date" placeholder={field.label} disabled />
        </div>
      );
    case "checkbox":
      return (
        <div className="mb-4">
          <div className="mb-1">
            {field.label}
            {field.is_required && <span className="text-red-500 ml-1">*</span>}
          </div>
          <div className="flex flex-col gap-2">
            {(field.options_meta && field.options_meta.length > 0
              ? field.options_meta.map((o) => o.label)
              : field.options || []
            ).map((label, i) => {
              const meta = field.options_meta?.[i] as any;
              return (
                <label key={i} className="flex items-center gap-2 text-sm">
                  <input type="checkbox" disabled /> {label}
                  {meta?.requires_qty && (
                    <div className="flex items-center gap-2 ml-2">
                      <span className="text-muted-foreground">{meta.qty_label || "Qty"}</span>
                      <Input
                        type="number"
                        className="w-24"
                        placeholder={(meta.default_qty ?? 1).toString()}
                        disabled
                      />
                      {meta.unit && <span className="text-muted-foreground">{meta.unit}</span>}
                    </div>
                  )}
                  {meta?.requires_text && (
                    <div className="flex items-center gap-2 ml-2">
                      <span className="text-muted-foreground">{meta.text_label || "Specify"}</span>
                      <Input
                        type="text"
                        className="w-48"
                        placeholder={meta.text_label || "Specify"}
                        disabled
                      />
                    </div>
                  )}
                </label>
              );
            })}
          </div>
        </div>
      );
    case "radio":
      return (
        <div className="mb-4">
          <div className="mb-1">
            {field.label}
            {field.is_required && <span className="text-red-500 ml-1">*</span>}
          </div>
          <div className="flex flex-col gap-2">
            {(field.options_meta && field.options_meta.length > 0
              ? field.options_meta.map((o) => o.label)
              : field.options || []
            ).map((label, i) => {
              const meta = field.options_meta?.[i] as any;
              return (
                <label key={i} className="flex items-center gap-2 text-sm">
                  <input type="radio" disabled /> {label}
                  {meta?.requires_qty && (
                    <div className="flex items-center gap-2 ml-2">
                      <span className="text-muted-foreground">{meta.qty_label || "Qty"}</span>
                      <Input type="number" className="w-24" placeholder={(meta.default_qty ?? 1).toString()} disabled />
                      {meta.unit && <span className="text-muted-foreground">{meta.unit}</span>}
                    </div>
                  )}
                  {/* Radio: we do NOT show text input toggle per requirements */}
                </label>
              );
            })}
          </div>
        </div>
      );
    case "select":
      return (
        <div className="mb-4">
          <label className="block mb-1">
            {field.label}
            {field.is_required && <span className="text-red-500 ml-1">*</span>}
          </label>
          <select disabled className="border rounded px-2 py-1 w-full">
            <option value="">Select...</option>
            {(field.options_meta && field.options_meta.length > 0
              ? field.options_meta.map((o) => o.label)
              : field.options || []
            ).map((opt, i) => (
              <option key={i} value={String(opt)}>
                {String(opt)}
              </option>
            ))}
          </select>
        </div>
      );
    default:
      return null;
  }
}
