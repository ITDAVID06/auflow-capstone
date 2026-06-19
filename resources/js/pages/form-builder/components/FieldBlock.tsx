import React, { useRef, useState } from "react";
import axios from "axios";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { GripVertical, Trash2, Copy, Upload, Link, Loader2, X, HelpCircle } from "lucide-react";
import { FormField, OptionMeta } from "../types/formBuilderTypes";
import { OptionEditor } from "./OptionEditor";
import { TableFieldBuilder } from "./TableFieldBuilder";
import { router } from "@inertiajs/react";
import { resolveImageFieldUrl } from "@/utils/imageFieldUrl";
import {
  isChoiceFieldType,
  isNonInputFieldType,
  supportsAdvancedMeta,
} from "../config/fieldTypeRegistry";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";

/**
 * Image upload/URL editor for image field type
 */
function ImageUploadEditor({
  field,
  onChange,
  locked,
}: {
  field: FormField;
  onChange: (updated: Partial<FormField>) => void;
  locked: boolean;
}) {
  const [uploadMode, setUploadMode] = useState<"url" | "upload">("url");
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith("image/")) {
      setUploadError("Please select an image file");
      return;
    }

    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
      setUploadError("Image must be less than 5MB");
      return;
    }

    setUploading(true);
    setUploadError(null);

    const formData = new FormData();
    formData.append("image", file);

    try {
      const { data } = await axios.post(route("admin.forms.upload-image"), formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      if (data.success) {
        onChange({
          field_options: {
            ...field.field_options,
            image_url: resolveImageFieldUrl({ imageUrl: data.url, imagePath: data.path }),
            image_path: data.path,
          },
        });
      } else {
        setUploadError(data.message || "Upload failed");
      }
    } catch (error: any) {
      const message = error?.response?.data?.message || "Failed to upload image. Please try again.";
      setUploadError(message);
    } finally {
      setUploading(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }
    }
  };

  const handleRemoveImage = () => {
    onChange({
      field_options: {
        ...field.field_options,
        image_url: "",
        image_path: undefined,
      },
    });
    setUploadError(null);
  };

  const currentImageUrl = resolveImageFieldUrl({
    imageUrl: field.field_options?.image_url,
    imagePath: field.field_options?.image_path,
  });
  const hasImage = !!currentImageUrl;

  return (
    <div className="mt-3 space-y-3 border-t pt-3">
      {/* Mode Toggle */}
      <div className="flex items-center gap-2 border-b pb-2">
        <Button
          type="button"
          size="sm"
          variant={uploadMode === "url" ? "default" : "ghost"}
          onClick={() => setUploadMode("url")}
          disabled={locked}
          className="h-7 px-3 text-xs"
        >
          <Link className="w-3 h-3 mr-1.5" />
          URL
        </Button>
        <Button
          type="button"
          size="sm"
          variant={uploadMode === "upload" ? "default" : "ghost"}
          onClick={() => setUploadMode("upload")}
          disabled={locked}
          className="h-7 px-3 text-xs"
        >
          <Upload className="w-3 h-3 mr-1.5" />
          Upload
        </Button>
      </div>

      {/* URL Mode */}
      {uploadMode === "url" && (
        <div>
          <Label htmlFor={`image-url-${field.id}`} className="text-xs font-medium">
            Image URL
          </Label>
          <Input
            id={`image-url-${field.id}`}
            value={currentImageUrl}
            onChange={(e) =>
              onChange({
                field_options: { ...field.field_options, image_url: e.target.value },
              })
            }
            placeholder="https://example.com/image.jpg"
            disabled={locked}
            className="mt-1 text-sm"
          />
        </div>
      )}

      {/* Upload Mode */}
      {uploadMode === "upload" && (
        <div className="space-y-2">
          <Label className="text-xs font-medium">Upload Image</Label>
          {!hasImage ? (
            <div>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                onChange={handleFileSelect}
                disabled={locked || uploading}
                className="hidden"
              />
              <Button
                type="button"
                variant="outline"
                onClick={() => fileInputRef.current?.click()}
                disabled={locked || uploading}
                className="w-full"
              >
                {uploading ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    Uploading...
                  </>
                ) : (
                  <>
                    <Upload className="w-4 h-4 mr-2" />
                    Choose Image
                  </>
                )}
              </Button>
              <p className="text-xs text-muted-foreground mt-1">
                Max 5MB • JPG, PNG, GIF, WebP
              </p>
            </div>
          ) : (
            <div className="relative border rounded-lg p-2 bg-muted/30">
              <img
                src={currentImageUrl}
                alt="Preview"
                className="max-h-32 mx-auto rounded"
              />
              <Button
                type="button"
                size="sm"
                variant="destructive"
                onClick={handleRemoveImage}
                disabled={locked}
                className="absolute top-1 right-1 h-6 w-6 p-0"
              >
                <X className="w-3 h-3" />
              </Button>
            </div>
          )}
          {uploadError && (
            <p className="text-xs text-destructive">{uploadError}</p>
          )}
        </div>
      )}

      {/* Alt Text */}
      <div>
        <Label htmlFor={`image-alt-${field.id}`} className="text-xs font-medium">
          Alt Text (optional)
        </Label>
        <Input
          id={`image-alt-${field.id}`}
          value={field.field_options?.image_alt || ""}
          onChange={(e) =>
            onChange({
              field_options: { ...field.field_options, image_alt: e.target.value },
            })
          }
          placeholder="Describe the image..."
          disabled={locked}
          className="mt-1 text-sm"
        />
      </div>

      {/* Width & Alignment */}
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label htmlFor={`image-width-${field.id}`} className="text-xs font-medium">
            Width
          </Label>
          <Select
            value={field.field_options?.image_width || "medium"}
            onValueChange={(value) =>
              onChange({
                field_options: { ...field.field_options, image_width: value as any },
              })
            }
            disabled={locked}
          >
            <SelectTrigger id={`image-width-${field.id}`} className="mt-1 h-8 text-sm">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="small">Small</SelectItem>
              <SelectItem value="medium">Medium</SelectItem>
              <SelectItem value="large">Large</SelectItem>
              <SelectItem value="full">Full Width</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label htmlFor={`image-align-${field.id}`} className="text-xs font-medium">
            Alignment
          </Label>
          <Select
            value={field.field_options?.image_alignment || "center"}
            onValueChange={(value) =>
              onChange({
                field_options: { ...field.field_options, image_alignment: value as any },
              })
            }
            disabled={locked}
          >
            <SelectTrigger id={`image-align-${field.id}`} className="mt-1 h-8 text-sm">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="left">Left</SelectItem>
              <SelectItem value="center">Center</SelectItem>
              <SelectItem value="right">Right</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>
  );
}

export function FieldBlock({
  field,
  onChange,
  onDelete,
  onDuplicate,
  dragHandleProps,
  locked = false,
  allFields = [],
}: {
  field: FormField;
  onChange: (updated: Partial<FormField>) => void;
  onDelete: () => void;
  onDuplicate?: () => void;
  dragHandleProps?: React.HTMLAttributes<HTMLDivElement>;
  locked?: boolean;
  /** All form fields — passed through for conditional logic editor. */
  allFields?: FormField[];
}) {
  const handleLabelChange = (e: React.ChangeEvent<HTMLInputElement>) =>
    onChange({ label: e.target.value });

  const handlePlaceholderChange = (e: React.ChangeEvent<HTMLInputElement>) =>
    onChange({ placeholder: e.target.value });

  const handleRequiredChange = (checked: boolean) =>
    onChange({ is_required: checked });

  // --- helpers to convert simple <-> meta
  const toMeta = (simple: string[]): OptionMeta[] =>
    (simple ?? []).map((label) => ({
      label,
      requires_qty: false,
      qty_label: "Qty",
      min_qty: 0,
      max_qty: null,
      step: 1,
      default_qty: 1,
      unit: "pcs",
      requires_text: false,
      text_label: "Specify",
    }));

  const toSimple = (meta: OptionMeta[] | undefined): string[] =>
    (meta ?? []).map((o) => o.label || "");

  const isChoicesField = isChoiceFieldType(field.data_type);

  // Advanced options are only supported for radio/checkbox
  const supportsAdvanced = supportsAdvancedMeta(field.data_type);

  // We treat "meta mode" as: options_meta is a non-empty array
  const metaMode = isChoicesField && Array.isArray((field as any).options_meta) && (field as any).options_meta.length > 0;

  const handleModeToggle = (enableMeta: boolean) => {
    if (!supportsAdvanced || locked) return;

    if (enableMeta) {
      const converted = toMeta(field.options ?? []);
      onChange({
        options_meta: converted,
        options: [],
      });
    } else {
      const converted = toSimple((field as any).options_meta ?? []);
      onChange({
        options: converted,
        options_meta: undefined,
      });
    }
  };

  const dateRangeDisabled = !!field.use_slots; // when using slots, force single date

  const isNonInputField = isNonInputFieldType(field.data_type);

  return (
    <div className="group border border-border/60 rounded-lg bg-card p-4 mb-4 hover:border-border focus-within:ring-1 focus-within:ring-border/80 transition-colors duration-150">
      {/* Top row */}
      <div className="flex items-center gap-2 mb-2">
        <div
          {...dragHandleProps}
          className="cursor-grab text-muted-foreground flex-shrink-0 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity duration-200"
          tabIndex={-1}
          aria-disabled={locked}
        >
          <GripVertical size={18} />
        </div>
        <Input
          className="font-semibold text-base flex-1"
          value={field.label || ""}
          placeholder={`New ${field.data_type} field`}
          onChange={handleLabelChange}
          disabled={locked}
        />
        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity duration-200">
          {onDuplicate && (
            <Button
              variant="ghost"
              size="icon"
              className="text-muted-foreground hover:text-foreground hover:bg-muted flex-shrink-0"
              onClick={onDuplicate}
              tabIndex={-1}
              disabled={locked}
              title="Duplicate field"
            >
              <Copy size={18} />
            </Button>
          )}
          <Button
            variant="ghost"
            size="icon"
            className="text-muted-foreground hover:text-destructive hover:bg-muted flex-shrink-0"
            onClick={onDelete}
            tabIndex={-1}
            disabled={locked}
          >
            <Trash2 size={18} />
          </Button>
        </div>
      </div>

      {/* Placeholder for text-like inputs */}
      {["text", "number", "email", "phone", "date", "textarea"].includes(field.data_type) && (
        <Input
          className="text-sm"
          value={field.placeholder || ""}
          placeholder="Placeholder (optional)"
          onChange={handlePlaceholderChange}
          disabled={locked}
        />
      )}

      {/* Options editor (content only; mode toggle below if supported) */}
      {isChoicesField && (
        <div className="mt-2">
          <OptionEditor
            fieldType={field.data_type}
            mode={metaMode ? "meta" : "simple"}
            options={field.options || []}
            onChange={(opts) =>
              onChange({
                options: opts,
                options_meta: undefined,
              })
            }
            optionsMeta={(field as any).options_meta || []}
            onChangeMeta={(opts) =>
              onChange({
                options_meta: opts,
              })
            }
            disabled={locked}
          />
        </div>
      )}

      {/* Table field configuration */}
      {field.data_type === "table" && (
        <div className="mt-3">
          <TableFieldBuilder
            fieldOptions={field.field_options || {}}
            onChange={(options) => onChange({ field_options: options })}
            disabled={locked}
          />
        </div>
      )}

      {/* Section Break editor */}
      {field.data_type === "section" && (
        <div className="mt-3 space-y-3 border-t pt-3">
          <div>
            <Label htmlFor={`section-title-${field.id}`} className="text-xs font-medium">Section Title (optional)</Label>
            <Input
              id={`section-title-${field.id}`}
              value={field.field_options?.section_title || ""}
              onChange={(e) => onChange({ 
                field_options: { ...field.field_options, section_title: e.target.value }
              })}
              placeholder="e.g., Personal Information"
              disabled={locked}
              className="mt-1 text-sm"
            />
          </div>
          <div>
            <Label htmlFor={`section-desc-${field.id}`} className="text-xs font-medium">Section Description (optional)</Label>
            <Textarea
              id={`section-desc-${field.id}`}
              value={field.field_options?.section_description || ""}
              onChange={(e) => onChange({ 
                field_options: { ...field.field_options, section_description: e.target.value }
              })}
              placeholder="Add instructions or context for this section..."
              disabled={locked}
              className="mt-1 text-sm"
              rows={2}
            />
          </div>
        </div>
      )}

      {/* Heading/Title editor */}
      {field.data_type === "heading" && (
        <div className="mt-3 space-y-3 border-t pt-3">
          <div>
            <Label htmlFor={`heading-content-${field.id}`} className="text-xs font-medium">Content</Label>
            <Textarea
              id={`heading-content-${field.id}`}
              value={field.field_options?.heading_content || ""}
              onChange={(e) => onChange({ 
                field_options: { ...field.field_options, heading_content: e.target.value }
              })}
              placeholder="Add a title, heading, or instructions..."
              disabled={locked}
              className="mt-1 text-sm"
              rows={3}
            />
          </div>
          <div>
            <Label htmlFor={`heading-size-${field.id}`} className="text-xs font-medium">Size</Label>
            <Select
              value={field.field_options?.heading_size || "medium"}
              onValueChange={(value) => onChange({ 
                field_options: { ...field.field_options, heading_size: value as any }
              })}
              disabled={locked}
            >
              <SelectTrigger id={`heading-size-${field.id}`} className="mt-1 h-8 text-sm">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="small">Small</SelectItem>
                <SelectItem value="medium">Medium</SelectItem>
                <SelectItem value="large">Large</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
      )}

      {/* Image editor */}
      {field.data_type === "image" && (
        <ImageUploadEditor
          field={field}
          onChange={onChange}
          locked={locked}
        />
      )}

      {/* Help Text - for all field types except non-input types */}
      {!isNonInputField && (
        <div className="mt-3">
          <Label htmlFor={`help-text-${field.id}`} className="text-xs font-medium text-muted-foreground">
            Help Text (optional)
          </Label>
          <Textarea
            id={`help-text-${field.id}`}
            value={field.help_text || ""}
            onChange={(e) => onChange({ help_text: e.target.value })}
            placeholder="Add description or instructions..."
            disabled={locked}
            className="mt-1 text-sm"
            rows={2}
          />
        </div>
      )}

      {/* Required toggle - only for input fields */}
      {!isNonInputField && (
        <div className="flex items-center gap-2 mt-3 ml-2">
          <Switch
            checked={field.is_required}
            onCheckedChange={handleRequiredChange}
            disabled={locked}
          />
          <span className="text-sm text-muted-foreground">Required</span>
        </div>
      )}

      {/* Auto-fill Name toggle - only for text fields */}
      {field.data_type === "text" && (
        <div className="flex items-center gap-2 mt-2 ml-2">
          <Switch
            checked={field.field_options?.auto_fill_name || false}
            onCheckedChange={(checked) =>
              onChange({
                field_options: {
                  ...field.field_options,
                  auto_fill_name: checked,
                },
              })
            }
            disabled={locked}
            id={`auto-fill-name-${field.id}`}
          />
          <label htmlFor={`auto-fill-name-${field.id}`} className="text-sm text-muted-foreground">
            Auto-fill with user's full name (Last name, First name)
          </label>
        </div>
      )}

      {/* Public visibility toggle - only for input fields */}
      {!isNonInputField && (
        <div className="flex items-center gap-2 mt-2 ml-2">
          <Switch
            checked={field.is_publicly_verifiable ?? true}
            onCheckedChange={(checked) => onChange({ is_publicly_verifiable: checked })}
            disabled={locked}
            id={`is-public-${field.id}`}
          />
          <label htmlFor={`is-public-${field.id}`} className="text-sm text-muted-foreground">
            Visible on public verification page
          </label>
          <TooltipProvider delayDuration={200}>
            <Tooltip>
              <TooltipTrigger asChild>
                <HelpCircle size={13} className="text-muted-foreground/60 cursor-help flex-shrink-0" />
              </TooltipTrigger>
              <TooltipContent side="right" className="max-w-xs">
                When off, this field's value is hidden from anyone viewing the public verification link — replaced with "Redacted for Privacy".
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        </div>
      )}

      {/* Sensitive field toggle - only for input fields */}
      {!isNonInputField && (
        <div className="flex items-center gap-2 mt-2 ml-2">
          <Switch
            checked={field.is_sensitive ?? false}
            onCheckedChange={(checked) => onChange({ is_sensitive: checked })}
            disabled={locked}
            id={`is-sensitive-${field.id}`}
          />
          <label htmlFor={`is-sensitive-${field.id}`} className="text-sm text-muted-foreground">
            Sensitive — mask value on public verification page
          </label>
          <TooltipProvider delayDuration={200}>
            <Tooltip>
              <TooltipTrigger asChild>
                <HelpCircle size={13} className="text-muted-foreground/60 cursor-help flex-shrink-0" />
              </TooltipTrigger>
              <TooltipContent side="right" className="max-w-xs">
                When on, unauthenticated viewers see a partially masked value (e.g. j***n@auf.edu.ph) instead of the full value. Has no effect if the field is already hidden above.
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        </div>
      )}

      {/* NEW: Mode toggle — only for radio/checkbox */}
      {isChoicesField && supportsAdvanced && (
        <div className="flex items-center gap-2 mt-3 ml-2">
          <Switch
            checked={!!metaMode}
            onCheckedChange={(checked) => handleModeToggle(checked)}
            disabled={locked}
            id={`enable-qty-${field.id}`}
          />
          <label htmlFor={`enable-qty-${field.id}`} className="text-sm text-muted-foreground">
            Enable advanced options (qty/text)
          </label>
        </div>
      )}

      {/* Date field extras */}
      {field.data_type === "date" && (
        <>
          <div className="flex items-center gap-2 mt-2 ml-2">
            <Switch
              checked={field.use_slots || false}
              onCheckedChange={(checked) =>
                onChange({
                  use_slots: checked,
                  // enabling slots → force single mode
                  // disabling slots → clear slot-specific settings so the field
                  //   doesn't silently vanish from the student submission form
                  ...(checked
                    ? { date_mode: "single" }
                    : { require_facility: false }),
                })
              }
              disabled={locked}
            />
            <span className="text-sm text-muted-foreground">
              Use Date + Time Slots
            </span>
          </div>

          {field.use_slots && (
            <div className="flex items-center gap-2 mt-2 ml-2">
              <Switch
                checked={field.require_facility || false}
                onCheckedChange={(checked) => onChange({ require_facility: checked })}
                disabled={locked}
              />
              <span className="text-sm text-muted-foreground">Require Facility</span>
            </div>
          )}

          {/* Date mode (range) toggle */}
          <div className="flex items-center gap-2 mt-2 ml-2">
            <Switch
              checked={(field.date_mode || "single") === "range"}
              onCheckedChange={(checked) => onChange({ date_mode: checked ? "range" : "single" })}
              disabled={locked || dateRangeDisabled}
            />
            <span className="text-sm text-muted-foreground">
              Date range (From–To){dateRangeDisabled && " — disabled when using time slots"}
            </span>
          </div>
        </>
      )}

    </div>
  );
}
