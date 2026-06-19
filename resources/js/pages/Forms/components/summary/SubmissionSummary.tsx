import React from "react";
import { format } from "date-fns";
import { FileText } from "lucide-react";
import { resolveImageFieldUrl } from "@/utils/imageFieldUrl";
import logoUrl from "@/assets/auf_logo.png";

import type {
  ExistingAttachment,
  FormField,
  FormPayload,
  MultiMetaSelection,
  SelectedSlot,
  SingleMetaSelection,
} from "@/types/form";
import { resolveMetaLookup, toCompact } from "../../utils/meta";

interface SubmissionSummaryProps {
  form: FormPayload;
  values: Record<string, unknown>;
  slots: SelectedSlot[];
  plainDates: Date[];
  dateRanges: Array<{ from: Date; to: Date }>;
  attachments: File[];
  existingAttachments?: ExistingAttachment[];
  requireFacility: boolean;
  facilities: { id: number; name: string }[];
}

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

const toDisplayText = (value: unknown): string => {
  if (value === null || typeof value === "undefined") {
    return "";
  }

  if (typeof value === "string" || typeof value === "number") {
    return String(value);
  }

  if (typeof value === "boolean") {
    return value ? "Yes" : "No";
  }

  if (Array.isArray(value)) {
    return value.map((entry) => String(entry)).join(", ");
  }

  return "";
};

const formatMetaItem = (
  item: { value?: string; qty?: number; text?: string },
  lookup: Record<string, string>
) => {
  const value = (item.value ?? "").toString();
  const label = lookup[value] ?? value;
  const qty = typeof item.qty === "number" ? ` × ${toCompact(item.qty)}` : "";
  const text = item.text && item.text.trim() !== "" ? ` — "${item.text.trim()}"` : "";
  return `${label}${qty}${text}`;
};

const renderFieldSummary = (field: FormField, values: Record<string, unknown>) => {
  if (field.data_type === "date") {
    return null;
  }

  if (field.data_type === "section") {
    const options = toFieldOptions(field);
    const title = String(options.section_title ?? field.label ?? "").trim();
    const description = String(options.section_description ?? "").trim();

    return (
      <div key={field.id} className="col-span-full border-t border-gray-200 dark:border-gray-700 pt-5">
        {title && <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">{title}</h3>}
        {description && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>}
      </div>
    );
  }

  if (field.data_type === "heading") {
    const options = toFieldOptions(field);
    const content = String(options.heading_content ?? field.label ?? "");
    const size = String(options.heading_size ?? "medium");
    const sizeClass = size === "large" ? "text-xl font-semibold" : size === "small" ? "text-base" : "text-lg";

    return (
      <div key={field.id} className="col-span-full py-1">
        <p className={`${sizeClass} text-gray-900 dark:text-gray-100 whitespace-pre-wrap`}>{content}</p>
      </div>
    );
  }

  if (field.data_type === "image") {
    const options = toFieldOptions(field);
    const imageUrl = resolveImageFieldUrl({
      imageUrl: String(options.image_url ?? ""),
      imagePath: String(options.image_path ?? ""),
    });
    const imageAlt = String(options.image_alt ?? field.label ?? "Image");

    return (
      <div key={field.id} className="col-span-full space-y-1.5">
        <p className="text-xs text-gray-500 dark:text-gray-400">{field.label}</p>
        {imageUrl ? (
          <div className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 bg-gray-50 dark:bg-gray-800">
            <img src={imageUrl} alt={imageAlt} className="max-h-56 w-auto rounded" />
          </div>
        ) : (
          <div className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800">
            No image configured
          </div>
        )}
      </div>
    );
  }

  const value = values[field.field_name];

  if (field.data_type === "table") {
    const options = toFieldOptions(field);
    const columns = Array.isArray(options.table_columns) ? (options.table_columns as Array<{ id: string; label: string }>) : [];
    const tableRows = Array.isArray(value) ? (value as Array<Record<string, unknown>>) : [];

    return (
      <div key={field.id} className="col-span-full space-y-1.5">
        <p className="text-xs text-gray-500 dark:text-gray-400">{field.label}</p>
        {columns.length > 0 && tableRows.length > 0 ? (
          <div className="rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden bg-gray-50 dark:bg-gray-800">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                  <tr>
                    {columns.map((column) => (
                      <th key={column.id} className="text-left px-3 py-2 text-xs font-semibold">
                        {column.label}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {tableRows.map((row, rowIndex) => (
                    <tr key={rowIndex} className="border-b border-gray-200 dark:border-gray-700 last:border-0">
                      {columns.map((column) => (
                        <td key={column.id} className="px-3 py-2">
                          {toDisplayText(row[column.id]) || "—"}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ) : (
          <div className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">—</div>
        )}
      </div>
    );
  }

  if (field.data_type === "file") {
    const fileName = value instanceof File ? value.name : "";

    return (
      <div key={field.id} className="space-y-1.5">
        <p className="text-xs text-gray-500 dark:text-gray-400">{field.label}</p>
        <div className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">{fileName || "—"}</div>
      </div>
    );
  }

  if (field.options_meta && ["checkbox", "radio", "select"].includes(field.data_type)) {
    const lookup = resolveMetaLookup(field.options_meta);

    if (field.data_type === "checkbox") {
      const selected: MultiMetaSelection = Array.isArray(value) ? (value as MultiMetaSelection) : [];
      const summary = selected
        .map((item) => formatMetaItem(item, lookup))
        .filter(Boolean)
        .join(", ");

      return (
        <div key={field.id} className="space-y-1.5">
          <p className="text-xs text-gray-500 dark:text-gray-400">{field.label}</p>
          <div className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">{summary || "—"}</div>
        </div>
      );
    }

    const selected = value as SingleMetaSelection | string | undefined;
    const summary =
      typeof selected === "string"
        ? lookup[selected] ?? selected
        : selected?.value
        ? formatMetaItem(selected, lookup)
        : "—";

    return (
      <div key={field.id} className="space-y-1.5">
        <p className="text-xs text-gray-500 dark:text-gray-400">{field.label}</p>
        <div className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">{summary}</div>
      </div>
    );
  }

  if (field.data_type === "checkbox" && field.options && field.options.length > 0) {
    const selected = Array.isArray(value) ? (value as string[]) : [];
    const summary = selected.length > 0 ? selected.join(", ") : "—";

    return (
      <div key={field.id} className="space-y-1.5">
        <p className="text-xs text-gray-500 dark:text-gray-400">{field.label}</p>
        <div className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">{summary}</div>
      </div>
    );
  }

  return (
    <div key={field.id} className="space-y-1.5">
      <p className="text-xs text-gray-500 dark:text-gray-400">{field.label}</p>
      <div className="rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">
        {toDisplayText(value) || "—"}
      </div>
    </div>
  );
};

const renderSlotsSummary = (
  form: FormPayload,
  dtSlots: SelectedSlot[],
  plainDates: Date[],
  dateRanges: Array<{ from: Date; to: Date }>,
  requireFacility: boolean,
  facilities: { id: number; name: string }[]
) => {
  if (!dtSlots.length && !plainDates.length && !dateRanges.length) {
    return null;
  }

  const slotDateField = form.fields.find((field) => field.data_type === "date" && field.use_slots);
  const plainDateField = form.fields.find(
    (field) => field.data_type === "date" && !field.use_slots && ((field.date_mode ?? "single") === "single")
  );
  const rangeDateField = form.fields.find(
    (field) => field.data_type === "date" && ((field.date_mode ?? "single") === "range")
  );

  return (
    <section className="space-y-3 border-b border-gray-200 dark:border-gray-700 pb-5">
      <div className="space-y-1">
        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Date Selections</h3>
        <p className="text-sm text-gray-500 dark:text-gray-400">Review selected date and time details before submitting.</p>
      </div>

      {slotDateField && dtSlots.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-xs text-gray-500 dark:text-gray-400">{slotDateField.label}</p>
          <div className="space-y-1 rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">
            {dtSlots.map((slot, index) => {
              const base = format(slot.date, "MMMM dd, yyyy");
              const time =
                slot.start_time && slot.end_time ? ` | ${slot.start_time} – ${slot.end_time}` : "";
              const facility =
                requireFacility && slot.facility_id
                  ? ` | ${
                      facilities.find((item) => String(item.id) === slot.facility_id)?.name ||
                      `Facility ${slot.facility_id}`
                    }`
                  : "";

              return (
                <div key={`dt-${index}`}>
                  {base}
                  {time}
                  {facility}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {plainDateField && plainDates.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-xs text-gray-500 dark:text-gray-400">{plainDateField.label}</p>
          <div className="space-y-1 rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">
            {plainDates.map((date, index) => (
              <div key={`plain-${index}`}>{format(date, "MMMM dd, yyyy")}</div>
            ))}
          </div>
        </div>
      )}

      {rangeDateField && dateRanges.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-xs text-gray-500 dark:text-gray-400">{rangeDateField.label}</p>
          <div className="space-y-1 rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">
            {dateRanges.map((range, index) => (
              <div key={`range-${index}`}>
                {format(range.from, "MMMM dd, yyyy")} – {format(range.to, "MMMM dd, yyyy")}
              </div>
            ))}
          </div>
        </div>
      )}
    </section>
  );
};

const renderGeneralAttachmentsSummary = (
  attachments: File[],
  existingAttachments?: ExistingAttachment[]
) => {
  const hasExisting = (existingAttachments?.length ?? 0) > 0;
  const hasNew = attachments.length > 0;

  if (!hasExisting && !hasNew) {
    return null;
  }

  return (
    <section className="space-y-3">
      <div className="space-y-1">
        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">General Attachments</h3>
        <p className="text-sm text-gray-500 dark:text-gray-400">Supporting files attached to this request.</p>
      </div>

      <div className="space-y-1 rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-800">
        {hasExisting &&
          existingAttachments!.map((file, index) => (
            <div key={`existing-${file.id}-${index}`} className="flex flex-col gap-0.5">
              <div className="flex items-center gap-2">
                <FileText className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                <span className="truncate">{file.original_name}</span>
              </div>
              {file.mime_type && (
                <span className="pl-6 text-xs text-gray-500 dark:text-gray-400">{file.mime_type}</span>
              )}
            </div>
          ))}

        {hasExisting && hasNew && <div className="h-px w-full bg-gray-200 dark:bg-gray-700" />}

        {attachments.map((file, index) => (
          <div key={`new-${file.name}-${index}`} className="flex items-center gap-2">
            <FileText className="h-4 w-4 text-gray-500 dark:text-gray-400" />
            <span className="truncate">{file.name}</span>
          </div>
        ))}
      </div>
    </section>
  );
};

export const SubmissionSummary: React.FC<SubmissionSummaryProps> = ({
  form,
  values,
  slots,
  plainDates,
  dateRanges,
  attachments,
  existingAttachments,
  requireFacility,
  facilities,
}) => {
  const sortedFields = [...form.fields].sort((a, b) => Number(a.field_order ?? 0) - Number(b.field_order ?? 0));

  return (
    <div className="space-y-5">
      <section className="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
        <div className="border-b border-gray-200 dark:border-gray-700 px-4 py-3">
          <div className="flex items-center gap-3">
            <img
              src={logoUrl}
              alt="AUF Logo"
              className="h-8 w-8 rounded-md object-contain ring-1 ring-gray-200 dark:ring-gray-700"
            />
            <div>
              <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">Angeles University Foundation</p>
              <p className="text-xs text-gray-500 dark:text-gray-400">Digital Document Management System</p>
            </div>
          </div>
        </div>

        <div className="space-y-5 px-4 py-4">
          <div className="space-y-1 border-b border-gray-200 dark:border-gray-700 pb-4">
            <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100">{form.form_name}</h2>
            {form.description ? <p className="text-sm text-gray-500 dark:text-gray-400">{form.description}</p> : null}
          </div>

          <section className="space-y-3 border-b border-gray-200 dark:border-gray-700 pb-5">
            <div className="space-y-1">
              <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Basic Information</h3>
              <p className="text-sm text-gray-500 dark:text-gray-400">Verify all entered details before final submission.</p>
            </div>

            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              {sortedFields.map((field) => renderFieldSummary(field, values))}
            </div>
          </section>

          {renderSlotsSummary(form, slots, plainDates, dateRanges, requireFacility, facilities)}
          {renderGeneralAttachmentsSummary(attachments, existingAttachments)}
        </div>
      </section>

      <p className="text-xs text-gray-500 dark:text-gray-400">
        Please review the information carefully. Click Go Back to edit anything before confirming submission.
      </p>
    </div>
  );
};
