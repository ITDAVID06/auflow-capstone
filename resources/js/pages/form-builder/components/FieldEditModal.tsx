import React, { useState } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { Button } from "@/components/ui/button";
import { OptionEditor } from "./OptionEditor";
import { FormField } from "../types/formBuilderTypes";

const FIELD_TYPE_OPTIONS = [
  { value: "text", label: "Text" },
  { value: "textarea", label: "Textarea" },
  { value: "number", label: "Number" },
  { value: "date", label: "Date" },
  { value: "select", label: "Dropdown" },
  { value: "radio", label: "Radio" },
  { value: "checkbox", label: "Checkbox" }
];

export function FieldEditModal({
  field,
  existingFields,
  onSave,
  onCancel
}: {
  field: Partial<FormField>;
  existingFields: FormField[];
  onSave: (field: FormField) => void;
  onCancel: () => void;
}) {
  const isEdit = !!field.id;
  const [values, setValues] = useState<Partial<FormField>>({
    ...field,
    field_name: field.field_name || ""
  });
  const [error, setError] = useState<string | null>(null);

  function handleChange<K extends keyof FormField>(key: K, value: FormField[K]) {
    setValues(v => ({ ...v, [key]: value }));
  }

  function validate(): string | null {
    if (!values.label?.trim()) return "Label is required.";
    if (!values.field_name?.trim()) return "Field name is required.";
    if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(values.field_name))
      return "Field name must be alphanumeric/underscore, no spaces, start with a letter/underscore.";
    if (existingFields.some(f => f.field_name === values.field_name && f.id !== values.id))
      return "Field name must be unique in this form.";
    if (!values.data_type) return "Field type is required.";
    if (["select", "radio"].includes(values.data_type) && (!values.options || values.options.length === 0))
      return "Options required for select/radio fields.";
    return null;
  }

  function handleSave() {
    const err = validate();
    if (err) return setError(err);
    // Generate a local ID if adding new
    const fieldData: FormField = {
      ...values,
      id: values.id || crypto.randomUUID(),
      field_order: values.field_order ?? existingFields.length,
      is_required: values.is_required ?? false,
      is_sensitive: values.is_sensitive ?? false,
      is_publicly_verifiable: values.is_publicly_verifiable ?? true,
      options: values.options ?? [],
      data_type: values.data_type ?? "text",
      label: values.label ?? "",
      field_name: values.field_name ?? "",
    } as FormField;
    onSave(fieldData);
  }

return (
  <Dialog open onOpenChange={onCancel}>
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{isEdit ? "Edit Field" : "Add Field"}</DialogTitle>
      </DialogHeader>
      <div className="space-y-3">
        <div>
          <label className="text-sm font-medium mb-1 block" htmlFor="field-label">
            Field Label
          </label>
          <Input
            id="field-label"
            placeholder="Full Name"
            value={values.label || ""}
            onChange={e => handleChange("label", e.target.value)}
            autoFocus
          />
        </div>
        <div>
          <label className="text-sm font-medium mb-1 block" htmlFor="field-name">
            Field Name
          </label>
          <Input
            id="field-name"
            placeholder="field_name (must be unique, no spaces)"
            value={values.field_name || ""}
            onChange={e => handleChange("field_name", e.target.value)}
          />
        </div>
        <div>
          <label className="text-sm font-medium mb-1 block" htmlFor="field-type">
            Field Type
          </label>
          <select
            id="field-type"
            className="block w-full border rounded p-2"
            value={values.data_type || ""}
            onChange={e => handleChange("data_type", e.target.value)}
          >
            <option value="">Select type</option>
            {FIELD_TYPE_OPTIONS.map(opt => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2">
          <Switch
            checked={!!values.is_required}
            onCheckedChange={checked => handleChange("is_required", checked)}
            id="field-required"
          />
          <label htmlFor="field-required">Required</label>
        </div>
        
        {/* Auto-fill Name toggle - only for text fields */}
        {values.data_type === "text" && (
          <div className="space-y-2 border rounded-md p-3 bg-muted/30">
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <label htmlFor="auto-fill-name" className="text-sm font-medium">
                  Auto-fill with User's Name
                </label>
                <p className="text-xs text-muted-foreground">
                  Automatically fill this field with "Last name, First name" of logged-in user
                </p>
              </div>
              <Switch
                id="auto-fill-name"
                checked={!!values.field_options?.auto_fill_name}
                onCheckedChange={checked => {
                  setValues(v => ({
                    ...v,
                    field_options: {
                      ...v.field_options,
                      auto_fill_name: checked,
                    },
                  }));
                }}
              />
            </div>
          </div>
        )}
        
        {/* Only show options editor for select/radio */}
        {["select", "radio"].includes(values.data_type || "") && (
          <OptionEditor
            fieldType={values.data_type || "select"}
            mode="simple"
            options={values.options || []}
            onChange={opts => handleChange("options", opts)}
          />
        )}

        {/* Sensitivity & visibility settings */}
        <div className="space-y-2 border rounded-md p-3 bg-muted/30">
          <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
            Verification Visibility
          </p>
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <label htmlFor="field-sensitive" className="text-sm font-medium">
                Partially Mask on Snapshot
              </label>
              <p className="text-xs text-muted-foreground">
                Non-submitters see only the first and last character (e.g. J***e)
              </p>
            </div>
            <Switch
              id="field-sensitive"
              checked={!!values.is_sensitive}
              onCheckedChange={checked => handleChange("is_sensitive", checked)}
            />
          </div>
          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <label htmlFor="field-public" className="text-sm font-medium">
                Publicly Verifiable
              </label>
              <p className="text-xs text-muted-foreground">
                When off, value is fully hidden from non-submitters on the snapshot
              </p>
            </div>
            <Switch
              id="field-public"
              checked={values.is_publicly_verifiable !== false}
              onCheckedChange={checked => handleChange("is_publicly_verifiable", checked)}
            />
          </div>
        </div>

        {error && <div className="text-red-500">{error}</div>}
      </div>
      <DialogFooter>
        <Button variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        <Button onClick={handleSave}>
          {isEdit ? "Save Changes" : "Add Field"}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
);
}
