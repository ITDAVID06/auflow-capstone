import React from "react";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { CalendarIcon, FileText, Plus } from "lucide-react";
import { DynamicTableInput } from "@/pages/form-builder/components/DynamicTableInput";
import type { FormField } from "@/types/form";
import { resolveImageFieldUrl } from "@/utils/imageFieldUrl";

type FormMode = "live" | "preview";

interface FormRendererProps {
  fields: FormField[];
  mode: FormMode;
  className?: string;
}

/**
 * Shared form field renderer for both live forms and preview modals.
 * Ensures consistent styling between FormSubmissionPage and FormPreviewModal.
 */
export const FormRenderer: React.FC<FormRendererProps> = ({
  fields,
  mode,
  className = "",
}) => {
  const isPreview = mode === "preview";
  const fieldWrapperClass = "col-span-12 space-y-2";
  const labelClass = "block text-sm font-semibold text-zinc-800 dark:text-zinc-200";
  const helperTextClass = "text-xs leading-relaxed text-muted-foreground";
  const controlClass =
    "h-10 w-full rounded-md border-border/80 bg-background text-sm shadow-sm transition-colors focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/30";

  const renderField = (field: FormField) => {
    // Date fields — skip in live mode (handled by DateTimeSection/PlainDateSection/DateRangeSection);
    // render a faithful disabled representation in preview mode.
    if (field.data_type === "date") {
      if (!isPreview) return null;

      const isSlot = Boolean(field.use_slots);
      const isRange = !isSlot && field.date_mode === "range";

      if (isSlot) {
        return (
          <div key={field.id} className="col-span-12 space-y-4 border-b border-border/60 pb-6">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {field.label}
                {field.is_required && <span className="ml-1 text-red-500">*</span>}
              </h2>
              {field.help_text && <p className={helperTextClass}>{field.help_text}</p>}
            </div>
            <div className="grid items-end gap-3 md:grid-cols-8 lg:grid-cols-12">
              <div className="md:col-span-4 lg:col-span-5">
                <Label>Date</Label>
                <Button
                  type="button"
                  variant="outline"
                  disabled
                  className="min-w-[220px] w-full justify-start text-left"
                >
                  <CalendarIcon className="mr-2 h-4 w-4" />
                  Select date
                </Button>
              </div>
              <div className="md:col-span-2 lg:col-span-2">
                <Label>Start Time</Label>
                <Button
                  type="button"
                  variant="outline"
                  disabled
                  className="w-full justify-start text-left"
                >
                  Start time
                </Button>
              </div>
              <div className="md:col-span-2 lg:col-span-2">
                <Label>End Time</Label>
                <Button
                  type="button"
                  variant="outline"
                  disabled
                  className="w-full justify-start text-left"
                >
                  End time
                </Button>
              </div>
              {field.require_facility && (
                <div className="md:col-span-3 lg:col-span-2">
                  <Label>Facility</Label>
                  <Button
                    type="button"
                    variant="outline"
                    disabled
                    className="w-full justify-start text-left"
                  >
                    Select facility
                  </Button>
                </div>
              )}
              <div className="md:col-span-1 lg:col-span-1">
                <Button type="button" disabled className="w-full">
                  <Plus className="mr-2 h-4 w-4" /> Add
                </Button>
              </div>
            </div>
          </div>
        );
      }

      if (isRange) {
        return (
          <div key={field.id} className="col-span-12 space-y-4 border-b border-border/60 pb-6">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {field.label}
                {field.is_required && <span className="ml-1 text-red-500">*</span>}
              </h2>
              {field.help_text && <p className={helperTextClass}>{field.help_text}</p>}
            </div>
            <div className="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <Label>From</Label>
                <Button
                  type="button"
                  variant="outline"
                  disabled
                  className="w-full justify-start text-left"
                >
                  <CalendarIcon className="mr-2 h-4 w-4" />
                  Select start date
                </Button>
              </div>
              <div>
                <Label>To</Label>
                <Button
                  type="button"
                  variant="outline"
                  disabled
                  className="w-full justify-start text-left"
                >
                  <CalendarIcon className="mr-2 h-4 w-4" />
                  Select end date
                </Button>
              </div>
              <div className="sm:col-span-2 lg:col-span-1">
                <Button type="button" disabled className="w-full">
                  <Plus className="mr-2 h-4 w-4" /> Add Range
                </Button>
              </div>
            </div>
          </div>
        );
      }

      // Plain single date
      return (
        <div key={field.id} className="col-span-12 space-y-4 border-b border-border/60 pb-6">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {field.label}
              {field.is_required && <span className="ml-1 text-red-500">*</span>}
            </h2>
            {field.help_text && <p className={helperTextClass}>{field.help_text}</p>}
          </div>
          <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-end">
            <div className="sm:w-[260px]">
              <Label>Date</Label>
              <Button
                type="button"
                variant="outline"
                disabled
                className="w-full justify-start text-left"
              >
                <CalendarIcon className="mr-2 h-4 w-4" />
                Select date
              </Button>
            </div>
            <Button type="button" disabled>
              <Plus className="mr-2 h-4 w-4" /> Add Date
            </Button>
          </div>
        </div>
      );
    }

    // Render section break
    if (field.data_type === "section") {
      const title = (field.field_options as any)?.section_title || "";
      const description = (field.field_options as any)?.section_description || "";
      return (
        <div key={field.id} className="col-span-12 border-t border-border/70 pt-8">
          {title && <h2 className="text-lg font-semibold tracking-tight text-foreground">{title}</h2>}
          {description && <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{description}</p>}
        </div>
      );
    }

    // Render heading/title
    if (field.data_type === "heading") {
      const content = (field.field_options as any)?.heading_content || "";
      const size = (field.field_options as any)?.heading_size || "medium";
      const sizeClasses = {
        small: "text-base",
        medium: "text-lg",
        large: "text-xl font-semibold",
      };
      return (
        <div key={field.id} className="col-span-12 py-1">
          <div className={`${sizeClasses[size as keyof typeof sizeClasses]} text-foreground whitespace-pre-wrap`}>
            {content || field.label}
          </div>
        </div>
      );
    }

    // Render image
    if (field.data_type === "image") {
      const imageUrl = resolveImageFieldUrl({
        imageUrl: (field.field_options as any)?.image_url,
        imagePath: (field.field_options as any)?.image_path,
      });
      const imageAlt = (field.field_options as any)?.image_alt || field.label;
      const alignment = (field.field_options as any)?.image_alignment || "center";
      const width = (field.field_options as any)?.image_width || "medium";
      
      const alignmentClasses = {
        left: "justify-start",
        center: "justify-center",
        right: "justify-end",
      };
      const widthClasses = {
        small: "max-w-xs",
        medium: "max-w-md",
        large: "max-w-2xl",
        full: "max-w-full",
      };

      if (!imageUrl) {
        return (
          <div key={field.id} className="col-span-12 flex justify-center py-4">
            <div className="rounded-lg border-2 border-dashed border-border/70 p-8 text-center text-muted-foreground">
              <FileText className="mx-auto mb-2 h-12 w-12 opacity-40" />
              <p className="text-sm">No image URL specified</p>
            </div>
          </div>
        );
      }

      return (
        <div key={field.id} className={`col-span-12 flex py-4 ${alignmentClasses[alignment as keyof typeof alignmentClasses]}`}>
          <img 
            src={imageUrl} 
            alt={imageAlt}
            className={`${widthClasses[width as keyof typeof widthClasses]} h-auto rounded-lg`}
            onError={(e) => {
              const target = e.target as HTMLImageElement;
              target.style.display = 'none';
            }}
          />
        </div>
      );
    }

    const hasMetaOptions = field.options_meta && field.options_meta.length > 0;

    // Radio fields with meta options (stacked card style)
    if (field.data_type === "radio" && hasMetaOptions) {
      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <div className="flex flex-col gap-2">
            {field.options_meta!.map((option, idx) => {
              const optionValue = option.value || option.label;
              return (
                <div
                  key={idx}
                  className="rounded-md border border-border/70 bg-background/80 p-3 shadow-sm"
                >
                  <label className="flex items-center gap-2 text-sm font-medium">
                    <input
                      type="radio"
                      name={String(field.field_name)}
                      disabled={isPreview}
                      className={isPreview ? "opacity-50" : ""}
                    />
                    <span>{option.label}</span>
                  </label>

                  {option.requires_qty && (
                    <div className="mt-2 flex items-center gap-2 pl-6 text-xs text-muted-foreground">
                      <span>{option.qty_label || "Qty"}</span>
                      <Input
                        type="number"
                        className="h-8 w-24 rounded-md border-border/80 shadow-sm"
                        disabled={isPreview}
                        placeholder={String(option.default_qty ?? 1)}
                      />
                      {option.unit && <span>{option.unit}</span>}
                    </div>
                  )}

                  {option.requires_text && (
                    <div className="mt-2 flex items-center gap-2 pl-6 text-xs text-muted-foreground">
                      <span>{option.text_label || "Specify"}</span>
                      <Input
                        type="text"
                        className="h-8 w-[260px] rounded-md border-border/80 shadow-sm"
                        disabled={isPreview}
                      />
                    </div>
                  )}
                </div>
              );
            })}
          </div>
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // Checkbox fields with meta options (stacked card style)
    if (field.data_type === "checkbox" && hasMetaOptions) {
      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <div className="flex flex-col gap-2">
            {field.options_meta!.map((option, idx) => {
              return (
                <div
                  key={idx}
                  className="rounded-md border border-border/70 bg-background/80 p-3 shadow-sm"
                >
                  <label className="flex items-center gap-2 text-sm font-medium">
                    <input
                      type="checkbox"
                      disabled={isPreview}
                      className={isPreview ? "opacity-50" : ""}
                    />
                    <span>{option.label}</span>
                  </label>

                  {option.requires_qty && (
                    <div className="mt-2 flex items-center gap-2 pl-6 text-xs text-muted-foreground">
                      <span>{option.qty_label || "Qty"}</span>
                      <Input
                        type="number"
                        className="h-8 w-24 rounded-md border-border/80 shadow-sm"
                        disabled={isPreview}
                        placeholder={String(option.default_qty ?? 1)}
                      />
                      {option.unit && <span>{option.unit}</span>}
                    </div>
                  )}

                  {option.requires_text && (
                    <div className="mt-2 flex items-center gap-2 pl-6 text-xs text-muted-foreground">
                      <span>{option.text_label || "Specify"}</span>
                      <Input
                        type="text"
                        className="h-8 w-[260px] rounded-md border-border/80 shadow-sm"
                        disabled={isPreview}
                      />
                    </div>
                  )}
                </div>
              );
            })}
          </div>
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // Simple radio fields (stacked card style for consistency)
    if (field.data_type === "radio" && field.options && field.options.length > 0) {
      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <div className="flex flex-col gap-2">
            {field.options.map((option, idx) => (
              <div
                key={idx}
                className="rounded-md border border-border/70 bg-background/80 p-3 shadow-sm"
              >
                <label className="flex items-center gap-2 text-sm font-medium">
                  <input
                    type="radio"
                    name={String(field.field_name)}
                    disabled={isPreview}
                    className={isPreview ? "opacity-50" : ""}
                  />
                  <span>{option}</span>
                </label>
              </div>
            ))}
          </div>
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // Simple checkbox fields (stacked card style for consistency)
    if (field.data_type === "checkbox" && field.options && field.options.length > 0) {
      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <div className="flex flex-col gap-2">
            {field.options.map((option, idx) => (
              <div
                key={idx}
                className="rounded-md border border-border/70 bg-background/80 p-3 shadow-sm"
              >
                <label className="flex items-center gap-2 text-sm font-medium">
                  <input
                    type="checkbox"
                    disabled={isPreview}
                    className={isPreview ? "opacity-50" : ""}
                  />
                  <span>{option}</span>
                </label>
              </div>
            ))}
          </div>
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // Select fields
    if (field.data_type === "select" && field.options && field.options.length > 0) {
      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <select
            disabled={isPreview}
            className="flex h-10 w-full rounded-md border border-border/80 bg-background px-3 py-2 text-sm shadow-sm transition-colors ring-offset-background focus-visible:border-ring focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <option value="">Select an option</option>
            {field.options.map((option, idx) => (
              <option key={idx} value={option}>
                {option}
              </option>
            ))}
          </select>
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // Text fields (with auto-fill support)
    if (field.data_type === "text") {
      const isAutoFill = field.field_options?.auto_fill_name;
      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <Input
            type="text"
            disabled={isPreview || isAutoFill}
            placeholder={field.placeholder || ""}
            className={`${controlClass} ${isAutoFill ? "cursor-not-allowed bg-muted opacity-75" : ""}`}
          />
          {isAutoFill && (
            <p className={`${helperTextClass} italic`}>
              This field is auto-filled with your name and cannot be edited
            </p>
          )}
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // Email, number, phone fields
    if (["email", "number", "phone"].includes(field.data_type)) {
      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <Input
            type={field.data_type === "phone" ? "tel" : field.data_type}
            disabled={isPreview}
            placeholder={field.placeholder || ""}
            className={controlClass}
          />
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // Textarea fields
    if (field.data_type === "textarea") {
      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <Textarea
            disabled={isPreview}
            placeholder={field.placeholder || ""}
            rows={4}
            className="min-h-[110px] rounded-md border-border/80 bg-background text-sm shadow-sm transition-colors focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/30"
          />
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // Table fields
    if (field.data_type === "table") {
      const columns = field.field_options?.table_columns || [];
      const minRows = field.field_options?.min_rows || 1;
      const maxRows = field.field_options?.max_rows || 10;

      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <DynamicTableInput
            columns={columns}
            value={[]}
            onChange={() => {}}
            minRows={minRows}
            maxRows={maxRows}
            disabled={isPreview}
          />
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // File upload fields
    if (field.data_type === "file") {
      return (
        <div key={field.id} className={fieldWrapperClass}>
          <Label className={labelClass}>
            {field.label}
            {field.is_required && <span className="ml-1 text-red-500">*</span>}
          </Label>
          <div className="space-y-4">
            <label
              className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-md border border-blue-200/70 bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-700 transition-colors hover:bg-blue-100 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20 sm:w-auto"
            >
              <Plus className="h-4 w-4" />
              Choose Files to Upload
              <input
                type="file"
                className="hidden"
                disabled={isPreview}
                multiple
              />
            </label>
            <div className="rounded-lg border-2 border-dashed border-border/70 bg-muted/20 p-7 text-center">
              <FileText className="mx-auto mb-3 h-8 w-8 text-muted-foreground/70" />
              <p className="text-sm font-medium text-foreground/90">No attachments yet</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Click &ldquo;Choose Files&rdquo; above to add documents
              </p>
            </div>
          </div>
          {field.help_text && (
            <p className={helperTextClass}>{field.help_text}</p>
          )}
        </div>
      );
    }

    // Fallback for unknown field types
    return (
      <div key={field.id} className={fieldWrapperClass}>
        <Label className={labelClass}>
          {field.label}
          {field.is_required && <span className="ml-1 text-red-500">*</span>}
        </Label>
        <Input
          type="text"
          disabled={isPreview}
          placeholder={field.placeholder || ""}
          className={controlClass}
        />
        {field.help_text && (
          <p className={helperTextClass}>{field.help_text}</p>
        )}
      </div>
    );
  };

  return (
    <section className={`space-y-6 border-b border-border/60 pb-8 ${className}`}>
      <div>
        <h2 className="text-base font-semibold text-foreground">Basic Information</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          {isPreview
            ? "Preview of how the form will appear to users."
            : "Fill out all required fields."}
        </p>
      </div>
      <div className="grid grid-cols-12 gap-x-6 gap-y-6">
        {fields.map(renderField).filter(Boolean)}
      </div>
    </section>
  );
};
