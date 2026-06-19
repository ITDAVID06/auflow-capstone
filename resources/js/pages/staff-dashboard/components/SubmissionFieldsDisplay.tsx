import React from "react";
import { Calendar, FileText, Download, ExternalLink } from "lucide-react";
import { normalizeValue } from "../utils/valueFormatters";
import { resolveImageFieldUrl } from "@/utils/imageFieldUrl";
import { formatDate, formatDateTime } from "@/utils/dateTime";
import logoUrl from "@/assets/auf_logo.png";
import PaperFormShell from "@/components/forms/PaperFormShell";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import type { SubmissionData, SubmissionField, SubmissionFormField } from "../types/submissionTypes";

interface SubmissionFieldsDisplayProps {
  submission: SubmissionData;
  facilities: Array<{ id: number; name: string }>;
  onFilePreview: (url: string, title: string, mime?: string) => void;
}

const prettyLabel = (raw: string): string => {
  if (!raw) return raw;
  return raw
    .split(/[-_\s]+/)
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
    .join(" ");
};

type CheckboxSelection = {
  value: string;
  qty?: number;
  text?: string;
};

export const SubmissionFieldsDisplay: React.FC<SubmissionFieldsDisplayProps> = ({
  submission,
  facilities,
  onFilePreview,
}) => {
  type RenderableField = SubmissionField & {
    options?: unknown;
    options_meta?: unknown;
    is_required?: boolean;
    help_text?: string | null;
  };

  const fields = React.useMemo<RenderableField[]>(() => {
    const submissionFields = Array.isArray(submission.fields) ? submission.fields : [];
    const formFields = Array.isArray(submission.form_fields) ? submission.form_fields : [];

    if (formFields.length === 0) {
      return submissionFields;
    }

    const submittedByFieldName = new Map<string, SubmissionField>();
    const submittedByLabel = new Map<string, SubmissionField>();

    submissionFields.forEach((field) => {
      if (field.field_name) {
        submittedByFieldName.set(field.field_name, field);
      }

      submittedByLabel.set(field.label, field);
    });

    return [...formFields]
      .sort((left, right) => Number(left.field_order ?? 0) - Number(right.field_order ?? 0))
      .map((formField) => {
        const submitted =
          submittedByFieldName.get(formField.field_name) ?? submittedByLabel.get(formField.label);

        return {
          field_name: formField.field_name,
          label: formField.label,
          type: formField.data_type,
          field_options: formField.field_options ?? null,
          options: formField.options,
          options_meta: formField.options_meta,
          is_required: formField.is_required,
          help_text: formField.help_text ?? null,
          value: submitted?.value ?? "",
        };
      });
  }, [submission.fields, submission.form_fields]);

  const parseJsonLike = (input: unknown): unknown => {
    let current: unknown = input;

    const tryParse = (value: string): unknown => {
      const attempts = [value, value.replace(/\\"/g, '"'), value.replace(/\\\\/g, '\\')];

      for (const candidate of attempts) {
        try {
          return JSON.parse(candidate);
        } catch {
          // keep trying
        }
      }

      return undefined;
    };

    for (let i = 0; i < 6; i++) {
      if (typeof current !== "string") {
        break;
      }

      const trimmed = current.trim();
      const looksJsonLike =
        trimmed.startsWith("[") ||
        trimmed.startsWith("{") ||
        (trimmed.startsWith('"') && trimmed.endsWith('"'));

      if (!looksJsonLike) {
        break;
      }

      const parsed = tryParse(trimmed);
      if (typeof parsed === "undefined") {
        break;
      }

      current = parsed;
    }

    return current;
  };

  const extractMetaLookup = (field: RenderableField): Record<string, string> => {
    const rawOptionsMeta = Array.isArray(field.options_meta)
      ? field.options_meta
      : Array.isArray((field.field_options as Record<string, unknown> | null)?.options_meta)
        ? ((field.field_options as Record<string, unknown>).options_meta as unknown[])
        : [];

    return rawOptionsMeta.reduce<Record<string, string>>((lookup, item) => {
      if (!item || typeof item !== "object") {
        return lookup;
      }

      const entry = item as Record<string, unknown>;
      const value = String(entry.value ?? "").trim();
      const label = String(entry.label ?? value).trim();
      if (value) {
        lookup[value] = label;
      }

      return lookup;
    }, {});
  };

  const parseCheckboxSelections = (value: unknown): CheckboxSelection[] => {
    const parsed = parseJsonLike(value);
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed
      .map((item): CheckboxSelection | null => {
        if (typeof item === "string") {
          return { value: item };
        }

        if (!item || typeof item !== "object") {
          return null;
        }

        const raw = item as Record<string, unknown>;
        const value = String(raw.value ?? raw.label ?? raw.name ?? "").trim();
        const qtyRaw = raw.qty ?? raw.Qty;
        const qty =
          typeof qtyRaw === "number"
            ? qtyRaw
            : typeof qtyRaw === "string" && qtyRaw.trim() !== "" && !Number.isNaN(Number(qtyRaw))
              ? Number(qtyRaw)
              : undefined;
        const text = String(raw.text ?? raw.Text ?? "").trim();

        if (!value && !text) {
          return null;
        }

        return {
          value: value || text,
          qty,
          text: text || undefined,
        };
      })
      .filter((item): item is CheckboxSelection => item !== null);
  };

  const formatCheckboxSelection = (selection: CheckboxSelection, lookup: Record<string, string>): string => {
    const label = lookup[selection.value] ?? prettyLabel(selection.value);
    const qtyPart = typeof selection.qty === "number" ? ` × ${selection.qty}` : "";
    const textPart = selection.text ? ` — "${selection.text}"` : "";
    return `${label}${qtyPart}${textPart}`;
  };

  const normalizePath = (raw: string): string => {
    const path = raw.trim();
    if (path.startsWith('/files/')) {
      return path.slice('/files/'.length);
    }

    return path.replace(/^\/+/, '');
  };

  const looksLikeStoredPath = (raw: string): boolean => {
    const path = normalizePath(raw);
    return /^(uploads|submissions|submissions_uploads|submissions_attachments)\//.test(path);
  };

  const extractPathsFromValue = (value: unknown): string[] => {
    if (typeof value === 'string') {
      const trimmed = value.trim();

      if (looksLikeStoredPath(trimmed)) {
        return [normalizePath(trimmed)];
      }

      if ((trimmed.startsWith('{') || trimmed.startsWith('[')) && trimmed.length > 1) {
        try {
          const parsed = JSON.parse(trimmed);
          return extractPathsFromValue(parsed);
        } catch {
          return [];
        }
      }

      return [];
    }

    if (Array.isArray(value)) {
      return value.flatMap((entry) => extractPathsFromValue(entry));
    }

    if (value && typeof value === 'object') {
      const record = value as Record<string, unknown>;
      const candidates = [record.path, record.file_path, record.url, record.value]
        .filter((candidate): candidate is string => typeof candidate === 'string' && candidate.trim() !== '');

      return candidates
        .filter((candidate) => looksLikeStoredPath(candidate))
        .map((candidate) => normalizePath(candidate));
    }

    return [];
  };

  const extractFilename = (path: string): string => {
    const cleaned = path.split('?')[0];
    return cleaned.split('/').pop() || 'Attachment';
  };

  const inferType = (field: SubmissionField): string => {
    const explicitType = (field.type || "").toLowerCase();
    if (explicitType.includes('file') || explicitType.includes('upload')) {
      return 'file';
    }

    if (explicitType) {
      return explicitType;
    }

    const inferredPaths = extractPathsFromValue(field.value);
    if (inferredPaths.length > 0) {
      return "file";
    }

    return "text";
  };

  // Extract non-file fields for display
  const nonFileFields = fields.filter((f) => {
    return inferType(f) !== "file";
  });

  const formFields = (Array.isArray(submission.form_fields) ? submission.form_fields : []) as SubmissionFormField[];
  const dateFormFields = formFields
    .filter((field) => field.data_type === "date")
    .sort((left, right) => Number(left.field_order ?? 0) - Number(right.field_order ?? 0));

  const plainDateFields = dateFormFields.filter(
    (field) => !field.use_slots && !field.require_facility && (field.date_mode ?? "single") !== "range"
  );
  const slotDateFields = dateFormFields.filter((field) => Boolean(field.use_slots));
  const slotDateFieldsWithFacility = slotDateFields.filter((field) => Boolean(field.require_facility));
  const slotDateFieldsWithoutFacility = slotDateFields.filter((field) => !field.require_facility);
  const rangeDateFields = dateFormFields.filter((field) => (field.date_mode ?? "single") === "range");

  const slots = Array.isArray(submission.slots) ? submission.slots : [];
  const slotsWithFacility = slots.filter((slot) => !!slot.facility_id);
  const slotsWithoutFacility = slots.filter((slot) => !slot.facility_id);
  const dateRanges = Array.isArray(submission.date_ranges) ? submission.date_ranges : [];

  const fieldValueMap = new Map<string, unknown>();
  fields.forEach((field) => {
    if (field.field_name) {
      fieldValueMap.set(field.field_name, field.value);
    }
  });

  // Helper to check if a value is table data
  const parseTableData = (
    value: unknown
  ): {
    isTable: boolean;
    data: Array<Record<string, unknown>>;
    columns: Array<{ id: string; label: string }>;
  } => {
    try {
      // Check if it's already an array (from JSON parsing)
      if (Array.isArray(value) && value.length > 0 && typeof value[0] === 'object' && value[0] !== null) {
        const columns = Object.keys(value[0]).map(key => ({
          id: key,
          label: prettyLabel(key)
        }));
        return { isTable: true, data: value as Array<Record<string, unknown>>, columns };
      }
      
      // Try to parse as JSON string
      if (typeof value === 'string' && value.trim().startsWith('[')) {
        const parsed = JSON.parse(value);
        if (Array.isArray(parsed) && parsed.length > 0 && typeof parsed[0] === 'object') {
          const columns = Object.keys(parsed[0]).map(key => ({
            id: key,
            label: prettyLabel(key)
          }));
          return { isTable: true, data: parsed as Array<Record<string, unknown>>, columns };
        }
      }
    } catch {
      // Not a table
    }
    return { isTable: false, data: [], columns: [] };
  };

  const fieldAttachments = fields.flatMap((field) => {
    if (inferType(field) !== 'file') {
      return [];
    }

    const paths = extractPathsFromValue(field.value);
    if (paths.length === 0) {
      return [];
    }

    const baseLabel = prettyLabel(field.label);

    return paths.map((path, index) => ({
      label: paths.length > 1 ? `${baseLabel} (${index + 1})` : baseLabel,
      path,
    }));
  });

  const genericAttachments = Array.isArray(submission.attachments)
    ? submission.attachments
        .map((attachment) => {
          const rawPath = String(attachment?.file_path || '').trim();
          if (!looksLikeStoredPath(rawPath)) {
            return null;
          }

          return {
            label: attachment?.original_name || extractFilename(rawPath),
            path: normalizePath(rawPath),
          };
        })
        .filter((attachment): attachment is { label: string; path: string } => Boolean(attachment))
    : [];

  const dynamicAttachments = [...fieldAttachments, ...genericAttachments].filter(
    (attachment, index, array) => array.findIndex((entry) => entry.path === attachment.path) === index
  );

  const toPrivateUrl = (path: string) => `/files/${normalizePath(path)}`;

  const isImage = (path: string) => {
    const imageExts = ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"];
    const ext = path.split(".").pop()?.toLowerCase() || "";
    return imageExts.includes(ext);
  };

  const isPdf = (path: string) => {
    const ext = path.split(".").pop()?.toLowerCase() || "";
    return ext === "pdf";
  };

  return (
    <div className="space-y-6" data-tour="review-details">
      <PaperFormShell
        orgName="Angeles University Foundation"
        systemName="Digital Document Management System"
        logoSrc={logoUrl}
        className="space-y-6"
      >
        <section className="space-y-4 pb-6 border-b border-border/60">
          <div>
            <h2 className="text-base font-semibold">Request Details</h2>
            <p className="text-sm text-muted-foreground">Submission timeline and requester information.</p>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-1">
              <p className="text-sm font-medium text-foreground">Submitted</p>
              <Input value={formatDateTime(submission.created_at)} readOnly />
            </div>
            <div className="space-y-1">
              <p className="text-sm font-medium text-foreground">Last Updated</p>
              <Input value={formatDateTime(submission.updated_at)} readOnly />
            </div>
            <div className="space-y-1 sm:col-span-2">
              <p className="text-sm font-medium text-foreground">Submitter</p>
              <Input value={submission.submitter || "—"} readOnly />
            </div>
          </div>
        </section>

        <section className="space-y-4 pt-4 pb-6 border-b border-border/60">
          <div>
            <h2 className="text-base font-semibold">Submission Details</h2>
            <p className="text-sm text-muted-foreground">Submitted field values from the request form.</p>
          </div>

          <div className="space-y-4">
            {nonFileFields.map((field, index) => {
              const { label, value } = field;
              const fieldType = inferType(field);
              const display = normalizeValue(value);
              const shownLabel = prettyLabel(label);
              const fieldOptions = (field.field_options ?? {}) as Record<string, unknown>;
              const fieldKey = field.field_name || `${label}-${index}`;
              const options = Array.isArray(field.options) ? field.options.map((option) => String(option)) : [];

              if (fieldType === "section") {
                const sectionTitle = String(fieldOptions["section_title"] || shownLabel || "Section");
                const sectionDescription = String(fieldOptions["section_description"] || "");

                return (
                  <div key={fieldKey} className="py-6 border-t-2 border-gray-300 dark:border-zinc-700">
                    <p className="text-lg font-semibold text-foreground">{sectionTitle}</p>
                    {sectionDescription && (
                      <p className="mt-1 text-sm text-muted-foreground">{sectionDescription}</p>
                    )}
                  </div>
                );
              }

              if (fieldType === "heading") {
                const headingText = String(fieldOptions["heading_content"] || shownLabel || "Heading");
                const headingSize = String(fieldOptions["heading_size"] || "medium");
                const headingClass =
                  headingSize === "large"
                    ? "text-xl font-semibold"
                    : headingSize === "small"
                    ? "text-base"
                    : "text-lg font-medium";

                return (
                  <div key={fieldKey} className="py-2">
                    <p className={`${headingClass} text-foreground whitespace-pre-wrap`}>{headingText}</p>
                  </div>
                );
              }

              if (fieldType === "image") {
                const imageUrl = resolveImageFieldUrl({
                  imageUrl: String(fieldOptions["image_url"] || ""),
                  imagePath: String(fieldOptions["image_path"] || ""),
                });
                const imageAlt = String(fieldOptions["image_alt"] || shownLabel || "Image");

                return (
                  <div key={fieldKey} className="space-y-2">
                    <p className="text-sm font-medium text-foreground">
                      {shownLabel}
                    </p>
                    {imageUrl ? (
                      <img
                        src={imageUrl}
                        alt={imageAlt}
                        className="max-h-72 w-auto rounded-md border border-border/60"
                      />
                    ) : (
                      <div className="break-words rounded-md border border-border/60 bg-background px-3 py-2 text-sm min-h-[2.5rem] flex items-center">
                        —
                      </div>
                    )}
                  </div>
                );
              }
              
              // Check if this is a table field
              const { isTable, data: tableData, columns: tableColumns } = parseTableData(value);
              
              const isLongContent = display && display.length > 100;

              if (fieldType === "date") {
                return null;
              }

              // Render table field
              if (fieldType === "table" && isTable && tableData.length > 0) {
                return (
                  <div key={fieldKey} className="space-y-2">
                    <p className="text-sm font-medium text-foreground">
                      {shownLabel}
                    </p>
                    <div className="rounded-md border border-border/60 bg-muted/50 overflow-hidden">
                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead className="bg-muted/70 border-b border-border/60">
                            <tr>
                              {tableColumns.map((col) => (
                                <th key={col.id} className="text-left px-3 py-2 text-xs font-semibold">
                                  {col.label}
                                </th>
                              ))}
                            </tr>
                          </thead>
                          <tbody>
                            {tableData.map((row, idx) => (
                              <tr key={idx} className="border-b border-border/60 last:border-0">
                                {tableColumns.map((col) => (
                                  <td key={col.id} className="px-3 py-2">
                                    {row[col.id] || "—"}
                                  </td>
                                ))}
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                );
              }

              if (fieldType === "radio" && options.length > 0) {
                const selected = display;

                return (
                  <div key={fieldKey} className="space-y-2">
                    <p className="text-sm font-medium text-foreground">
                      {shownLabel}
                      {field.is_required ? <span className="text-red-500 ml-1">*</span> : null}
                    </p>
                    <div className="flex flex-wrap gap-4">
                      {options.map((option) => (
                        <label key={`${fieldKey}-${option}`} className="inline-flex items-center gap-2 text-sm text-foreground">
                          <input type="radio" checked={selected === option} disabled readOnly />
                          <span>{option}</span>
                        </label>
                      ))}
                    </div>
                    {field.help_text ? <p className="text-xs text-muted-foreground">{field.help_text}</p> : null}
                  </div>
                );
              }

              if (fieldType === "checkbox" || fieldType.includes("checkbox") || fieldType.includes("multi")) {
                const selections = parseCheckboxSelections(value);
                const lookup = extractMetaLookup(field);
                const selectedValues = selections.map((item) => item.value);

                if (options.length === 0 && selections.length > 0) {
                  const formatted = selections.map((item) => formatCheckboxSelection(item, lookup)).join(", ");

                  return (
                    <div key={fieldKey} className="space-y-1">
                      <p className="text-sm font-medium text-foreground">
                        {shownLabel}
                        {field.is_required ? <span className="text-red-500 ml-1">*</span> : null}
                      </p>
                      <Input value={formatted} readOnly />
                      {field.help_text ? <p className="text-xs text-muted-foreground">{field.help_text}</p> : null}
                    </div>
                  );
                }

                return (
                  <div key={fieldKey} className="space-y-2">
                    <p className="text-sm font-medium text-foreground">
                      {shownLabel}
                      {field.is_required ? <span className="text-red-500 ml-1">*</span> : null}
                    </p>
                    <div className="space-y-1">
                      {options.map((option) => (
                        <label key={`${fieldKey}-${option}`} className="flex items-start gap-2 text-sm text-foreground">
                          <input type="checkbox" checked={selectedValues.includes(option)} disabled readOnly className="mt-0.5" />
                          <span>
                            {option}
                            {(() => {
                              const selected = selections.find((item) => item.value === option);
                              if (!selected || (!selected.text && typeof selected.qty !== "number")) {
                                return null;
                              }

                              return (
                                <span className="ml-2 text-xs text-muted-foreground">
                                  {formatCheckboxSelection(selected, lookup).replace(`${lookup[selected.value] ?? prettyLabel(selected.value)}`, "").trim()}
                                </span>
                              );
                            })()}
                          </span>
                        </label>
                      ))}

                      {selections
                        .filter((item) => !options.includes(item.value))
                        .map((item, itemIndex) => (
                          <div key={`${fieldKey}-extra-${itemIndex}`} className="text-sm text-foreground pl-6">
                            • {formatCheckboxSelection(item, lookup)}
                          </div>
                        ))}
                    </div>
                    {field.help_text ? <p className="text-xs text-muted-foreground">{field.help_text}</p> : null}
                  </div>
                );
              }

              if (fieldType === "textarea") {
                return (
                  <div key={fieldKey} className="space-y-1">
                    <p className="text-sm font-medium text-foreground">
                      {shownLabel}
                      {field.is_required ? <span className="text-red-500 ml-1">*</span> : null}
                    </p>
                    <Textarea value={display || ""} readOnly className="min-h-24" />
                    {field.help_text ? <p className="text-xs text-muted-foreground">{field.help_text}</p> : null}
                  </div>
                );
              }

              // Render regular field
              return (
                <div key={fieldKey} className="space-y-1">
                  <p className="text-sm font-medium text-foreground">
                    {shownLabel}
                    {field.is_required ? <span className="text-red-500 ml-1">*</span> : null}
                  </p>
                  {isLongContent ? (
                    <Textarea value={display || ""} readOnly className="min-h-24" />
                  ) : (
                    <Input value={display || ""} readOnly />
                  )}
                  {field.help_text ? <p className="text-xs text-muted-foreground">{field.help_text}</p> : null}
                </div>
              );
            })}
          </div>
        </section>

        {(dateFormFields.length > 0 || slots.length > 0 || dateRanges.length > 0) && (
          <section className="space-y-4 pb-6 border-b border-border/60">
            <h2 className="text-base font-semibold">Date Selections</h2>
            <div className="space-y-4">
              {plainDateFields.map((field) => {
                const rawValue = fieldValueMap.get(field.field_name);
                const value = normalizeValue(rawValue);
                if (!value) {
                  return null;
                }

                return (
                  <div key={`plain-${field.field_name}`} className="space-y-2">
                    <p className="text-sm font-medium text-foreground">{prettyLabel(field.label)}</p>
                    <div className="flex items-center gap-2 rounded border border-border/60 p-2 text-sm">
                      <Calendar className="h-4 w-4 text-muted-foreground" />
                      <span>{value}</span>
                    </div>
                  </div>
                );
              })}

              {slotDateFieldsWithoutFacility.map((field, fieldIndex) => {
                const scopedSlots =
                  slotDateFieldsWithoutFacility.length === 1
                    ? slotsWithoutFacility
                    : fieldIndex === 0
                      ? slotsWithoutFacility
                      : [];

                if (scopedSlots.length === 0) {
                  return null;
                }

                return (
                  <div key={`slot-basic-${field.field_name}`} className="space-y-2">
                    <p className="text-sm font-medium text-foreground">{prettyLabel(field.label)}</p>
                    <div className="space-y-2">
                      {scopedSlots.map((slot, index) => {
                        const dateStr = formatDate(slot.date);
                        const timeStr = slot.start_time && slot.end_time ? ` | ${slot.start_time} – ${slot.end_time}` : "";

                        return (
                          <div key={`slot-basic-${field.field_name}-${index}`} className="flex items-center gap-2 rounded border border-border/60 p-2 text-sm">
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                            <span>{dateStr}{timeStr}</span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                );
              })}

              {slotDateFieldsWithFacility.map((field, fieldIndex) => {
                const scopedSlots =
                  slotDateFieldsWithFacility.length === 1
                    ? slotsWithFacility
                    : fieldIndex === 0
                      ? slotsWithFacility
                      : [];

                if (scopedSlots.length === 0) {
                  return null;
                }

                return (
                  <div key={`slot-facility-${field.field_name}`} className="space-y-2">
                    <p className="text-sm font-medium text-foreground">{prettyLabel(field.label)}</p>
                    <div className="space-y-2">
                      {scopedSlots.map((slot, index) => {
                        const dateStr = formatDate(slot.date);
                        const timeStr = slot.start_time && slot.end_time ? ` | ${slot.start_time} – ${slot.end_time}` : "";
                        const facilityStr =
                          slot.facility_id && facilities.length > 0
                            ? ` | ${
                                facilities.find((facility) => String(facility.id) === String(slot.facility_id))?.name ||
                                `Facility ${slot.facility_id}`
                              }`
                            : "";

                        return (
                          <div key={`slot-facility-${field.field_name}-${index}`} className="flex items-center gap-2 rounded border border-border/60 p-2 text-sm">
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                            <span>{dateStr}{timeStr}{facilityStr}</span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                );
              })}

              {rangeDateFields.map((field, fieldIndex) => {
                const scopedRanges =
                  rangeDateFields.length === 1
                    ? dateRanges
                    : fieldIndex === 0
                      ? dateRanges
                      : [];

                if (scopedRanges.length === 0) {
                  return null;
                }

                return (
                  <div key={`range-${field.field_name}`} className="space-y-2">
                    <p className="text-sm font-medium text-foreground">{prettyLabel(field.label)}</p>
                    <div className="space-y-2">
                      {scopedRanges.map((range, index) => {
                        const startDate = range.start_date || range.start;
                        const endDate = range.end_date || range.end;
                        const startStr = formatDate(startDate ?? null);
                        const endStr = formatDate(endDate ?? null);

                        return (
                          <div key={`range-${field.field_name}-${index}`} className="flex items-center gap-2 rounded border border-border/60 p-2 text-sm">
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                            <span>{startStr} – {endStr}</span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                );
              })}
            </div>
          </section>
        )}

        <section className="pt-0">
          <div className="pb-4">
            <h2 className="text-base font-semibold">Attachments (Optional)</h2>
            <p className="text-sm text-muted-foreground">Upload supporting documents. You can select multiple files at once.</p>
            <p className="mt-1 text-xs text-muted-foreground">Accepted formats: JPG, JPEG, PNG, PDF, DOC, DOCX (Max 10MB per file)</p>
          </div>

          {dynamicAttachments.length > 0 ? (
            <div className="space-y-2">
              {dynamicAttachments.map(({ label, path }, i) => {
                const url = toPrivateUrl(path);
                const img = isImage(path);
                const pdf = isPdf(path);
                const canPreview = pdf || img;

                return (
                  <div
                    key={label + i}
                    className="rounded-lg border border-border/60 bg-background p-3 text-sm"
                  >
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-4">
                      <div className="flex-shrink-0">
                        {img ? (
                          <button
                            onClick={() => onFilePreview(url, label, "image/*")}
                            className="group relative block overflow-hidden rounded-md border border-border/60"
                            aria-label={`Preview ${label}`}
                          >
                            <img
                              src={url}
                              alt={label}
                              className="h-20 w-28 sm:h-24 sm:w-32 object-cover"
                            />
                            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center">
                              <div className="opacity-0 group-hover:opacity-100 transition-opacity bg-white/90 dark:bg-black/90 rounded-full p-2">
                                <ExternalLink className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                              </div>
                            </div>
                          </button>
                        ) : (
                          <div className="flex h-20 w-28 sm:h-24 sm:w-32 items-center justify-center rounded-md border border-border/60 bg-muted/50">
                            <FileText className="h-8 w-8 text-muted-foreground" />
                          </div>
                        )}
                      </div>

                      <div className="flex-1 min-w-0 space-y-3">
                        <div>
                          <p className="text-sm font-semibold truncate" title={label}>
                            {label}
                          </p>
                          <p className="text-xs text-muted-foreground mt-1">
                            {img ? "Image" : pdf ? "PDF Document" : "File"}
                          </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                          {canPreview && (
                            <button
                              onClick={() => onFilePreview(url, label, pdf ? "application/pdf" : "image/*")}
                              className="inline-flex items-center justify-center gap-1 rounded-md bg-primary hover:bg-primary/90 text-primary-foreground px-3 py-1.5 text-xs font-medium transition-colors"
                            >
                              <ExternalLink className="h-3 w-3" />
                              Preview
                            </button>
                          )}

                          <a
                            href={url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 py-2 text-xs font-medium transition-colors hover:bg-accent"
                          >
                            <ExternalLink className="h-3.5 w-3.5" />
                            Open in New Tab
                          </a>

                          <a
                            href={url}
                            download
                            className="inline-flex items-center justify-center gap-1.5 rounded-md bg-secondary text-secondary-foreground px-3 py-2 text-xs font-medium transition-colors hover:bg-secondary/80"
                          >
                            <Download className="h-3.5 w-3.5" />
                            Download
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            <div className="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
              <FileText className="mx-auto mb-3 h-8 w-8 text-gray-400" />
              <p className="text-sm text-muted-foreground">No attachments yet</p>
              <p className="mt-1 text-xs text-muted-foreground">No files were submitted with this request</p>
            </div>
          )}
        </section>
      </PaperFormShell>
    </div>
  );
};
