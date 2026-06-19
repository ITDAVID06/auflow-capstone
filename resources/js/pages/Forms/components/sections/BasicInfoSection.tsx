import React from "react";
import { FormFieldInput } from "@/components/forms/FormFieldInput";

import type {
  FormField,
  MultiMetaSelection,
  OptionMeta,
  SingleMetaSelection,
} from "@/types/form";
import { MetaCheckboxGroup } from "../fields/MetaCheckboxGroup";
import { MetaRadioGroup } from "../fields/MetaRadioGroup";
import { MetaSelectField } from "../fields/MetaSelectField";
import { DynamicTableInput } from "@/pages/form-builder/components/DynamicTableInput";
import { resolveImageFieldUrl } from "@/utils/imageFieldUrl";

interface BasicInfoSectionProps {
  fields: FormField[];
  values: Record<string, any>;
  onSimpleChange: (field: FormField, value: any) => void;
  onMetaCheckboxToggle: (field: FormField, option: OptionMeta, checked: boolean) => void;
  onMetaCheckboxQty: (field: FormField, option: OptionMeta, value: string) => void;
  onMetaCheckboxText: (field: FormField, option: OptionMeta, value: string) => void;
  onMetaSinglePick: (field: FormField, value: string) => void;
  onMetaSingleQty: (field: FormField, value: string) => void;
  onMetaSingleText: (field: FormField, value: string) => void;
  fieldErrors?: Record<string, string>;
}

export const BasicInfoSection: React.FC<BasicInfoSectionProps> = ({
  fields,
  values,
  onSimpleChange,
  onMetaCheckboxToggle,
  onMetaCheckboxQty,
  onMetaCheckboxText,
  onMetaSinglePick,
  onMetaSingleQty,
  onMetaSingleText,
  fieldErrors,
}) => {
  const toFieldOptions = (field: FormField): Record<string, unknown> => {
    const raw = field.field_options;

    if (!raw) {
      return {};
    }

    if (typeof raw === "string") {
      try {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === "object" ? parsed : {};
      } catch {
        return {};
      }
    }

    return typeof raw === "object" ? (raw as Record<string, unknown>) : {};
  };

  const renderField = (field: FormField) => {
    if (field.data_type === "date") return null;

    // Render section break
    if (field.data_type === "section") {
      const title = (field.field_options as any)?.section_title || "";
      const description = (field.field_options as any)?.section_description || "";
      return (
        <div key={field.id} className="py-4 border-t border-gray-200 dark:border-gray-700">
          {title && <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h2>}
          {description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>}
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
        <div key={field.id} className="py-2">
          <div className={`${sizeClasses[size as keyof typeof sizeClasses]} text-gray-900 dark:text-gray-100 whitespace-pre-wrap`}>
            {content || field.label}
          </div>
        </div>
      );
    }

    // Render image
    if (field.data_type === "image") {
      const fieldOptions = toFieldOptions(field);
      const imageUrl = resolveImageFieldUrl({
        imageUrl: String(fieldOptions.image_url ?? ""),
        imagePath: String(fieldOptions.image_path ?? ""),
      });
      const imageAlt = String(fieldOptions.image_alt ?? field.label);
      const alignment = String(fieldOptions.image_alignment ?? "center");
      const width = String(fieldOptions.image_width ?? "medium");
      
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
          <div key={field.id} className="py-4">
            <p className="mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">{field.label}</p>
            <div className="rounded-md border border-dashed border-gray-200 dark:border-gray-700 px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
              No image configured
            </div>
          </div>
        );
      }

      return (
        <div key={field.id} className={`py-4 flex ${alignmentClasses[alignment as keyof typeof alignmentClasses]}`}>
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

    // Handle table fields
    if (field.data_type === "table") {
      // Provide default columns if none configured (for backward compatibility)
      const columns = field.field_options?.table_columns || [
        { id: "col_1", label: "Column 1", type: "text" as const, required: false },
        { id: "col_2", label: "Column 2", type: "text" as const, required: false },
      ];
      const minRows = field.field_options?.min_rows || 1;
      const maxRows = field.field_options?.max_rows || 10;
      const tableValue = values[field.field_name] || [];

      return (
        <div key={field.id} className="space-y-2">
          <label className="text-sm font-medium block">
            {field.label}
            {field.is_required && <span className="text-red-500 ml-1">*</span>}
          </label>
          <DynamicTableInput
            columns={columns}
            value={Array.isArray(tableValue) ? tableValue : []}
            onChange={(value) => onSimpleChange(field, value)}
            minRows={minRows}
            maxRows={maxRows}
          />
          {field.help_text && (
            <p className="text-xs text-gray-500 dark:text-gray-400">{field.help_text}</p>
          )}
        </div>
      );
    }

    if (field.options_meta && ["checkbox", "radio", "select"].includes(field.data_type)) {
      if (field.data_type === "checkbox") {
        return (
          <MetaCheckboxGroup
            key={field.id}
            field={field}
            value={(values[field.field_name] as MultiMetaSelection) || []}
            onToggle={onMetaCheckboxToggle}
            onQtyChange={onMetaCheckboxQty}
            onTextChange={onMetaCheckboxText}
          />
        );
      }

      if (field.data_type === "radio") {
        return (
          <MetaRadioGroup
            key={field.id}
            field={field}
            value={values[field.field_name] as SingleMetaSelection | undefined}
            onSelect={onMetaSinglePick}
            onQtyChange={onMetaSingleQty}
            onTextChange={onMetaSingleText}
          />
        );
      }

      if (field.data_type === "select") {
        return (
          <MetaSelectField
            key={field.id}
            field={field}
            value={values[field.field_name] as SingleMetaSelection | undefined}
            onSelect={onMetaSinglePick}
          />
        );
      }
    }

    return (
      <div key={field.id} className="space-y-1">
        {/* Check if this is a text field with auto-fill enabled */}
        {field.data_type === "text" && field.field_options?.auto_fill_name ? (
          <div className="space-y-2">
            <label className="text-sm font-medium">
              {field.label}
              {field.is_required && <span className="text-red-500 ml-1">*</span>}
            </label>
            <input
              type="text"
              value={values[field.field_name] ?? ""}
              readOnly
              className="flex h-10 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm cursor-not-allowed opacity-75"
              placeholder={field.placeholder || ""}
            />
            <p className="text-xs text-gray-500 dark:text-gray-400 italic">
              This field is auto-filled with your name and cannot be edited
            </p>
          </div>
        ) : (
          <FormFieldInput
            field={field as any}
            value={
              field.data_type === "checkbox"
                ? values[field.field_name] ?? []
                : values[field.field_name] ?? ""
            }
            onChange={(value) => onSimpleChange(field, value)}
            error={fieldErrors?.[field.field_name]}
          />
        )}
        {field.help_text && (
          <p className="text-xs text-gray-500 dark:text-gray-400">{field.help_text}</p>
        )}
      </div>
    );
  };

  return (
    <section className="space-y-4 pb-6 border-b border-gray-200 dark:border-gray-700">
      <div>
        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">Basic Information</h2>
        <p className="text-sm text-gray-500 dark:text-gray-400">Fill out all required fields.</p>
      </div>
      <div className="space-y-4">
        {fields.map(renderField).filter(Boolean)}
      </div>
    </section>
  );
};
