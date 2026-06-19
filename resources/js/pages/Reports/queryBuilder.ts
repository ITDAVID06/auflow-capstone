import { Attachment, ReportFilterClause, ReportFiltersState } from "./types";

type SubmissionWithOptionalAttachments = {
  attachments?: unknown;
};

export const buildAttachmentMap = (
  submissions: SubmissionWithOptionalAttachments[] | null | undefined,
): Map<number, Attachment> => {
  const map = new Map<number, Attachment>();

  if (!Array.isArray(submissions)) {
    return map;
  }

  submissions.forEach((submission) => {
    const attachments = submission?.attachments;

    if (!Array.isArray(attachments)) {
      return;
    }

    attachments.forEach((attachment) => {
      if (!attachment || typeof attachment !== "object") {
        return;
      }

      const typedAttachment = attachment as Attachment;

      if (typeof typedAttachment.id !== "number") {
        return;
      }

      map.set(typedAttachment.id, typedAttachment);
    });
  });

  return map;
};

export const shouldIndexAttachments = (selectedColumnKeys: string[]): boolean =>
  selectedColumnKeys.includes("attachments");

export const sanitizeReportBuilderFilters = (filters: ReportFiltersState["filters"]) =>
  filters
    .filter((filter) => {
      if (!filter.column || !filter.operator) {
        return false;
      }

      if (filter.operator === "is_null" || filter.operator === "is_not_null") {
        return true;
      }

      if (filter.operator === "in") {
        // Accept when at least one non-empty comma-separated value exists
        const parts = splitInValues(filter.value);
        return parts.length > 0;
      }

      if (filter.operator === "between") {
        // Accept when both from and to values are provided
        const parts = Array.isArray(filter.value) ? filter.value : [];
        return parts.length === 2 && parts.every((v) => String(v ?? "").trim() !== "");
      }

      const scalar = Array.isArray(filter.value) ? "" : (filter.value ?? "");
      return Boolean(scalar.trim());
    })
    .map((filter): ReportFilterClause => {
      if (filter.operator === "is_null" || filter.operator === "is_not_null") {
        return { column: filter.column, operator: filter.operator, value: null };
      }

      if (filter.operator === "in") {
        return {
          column: filter.column,
          operator: filter.operator,
          value: splitInValues(filter.value),
        };
      }

      if (filter.operator === "between") {
        const parts = Array.isArray(filter.value) ? filter.value : ["", ""];
        return {
          column: filter.column,
          operator: filter.operator,
          value: [String(parts[0] ?? "").trim(), String(parts[1] ?? "").trim()],
        };
      }

      const scalar = Array.isArray(filter.value) ? "" : (filter.value ?? "");
      return {
        column: filter.column,
        operator: filter.operator,
        value: scalar.trim(),
      };
    });

/** Split a comma-separated string (or existing array) into trimmed, non-empty parts. */
const splitInValues = (value: string | string[] | null | undefined): string[] => {
  if (Array.isArray(value)) {
    return value.map((v) => String(v).trim()).filter((v) => v !== "");
  }
  return String(value ?? "")
    .split(",")
    .map((v) => v.trim())
    .filter((v) => v !== "");
};

export const buildReportQueryParams = (filters: ReportFiltersState): Record<string, unknown> => {
  const params: Record<string, unknown> = {
    form_id: filters.form_id,
    per_page: filters.per_page,
    page: filters.page,
  };

  if (filters.date_from) {
    params.date_from = filters.date_from;
  }

  if (filters.date_to) {
    params.date_to = filters.date_to;
  }

  if (filters.submission_status) {
    params.submission_status = filters.submission_status;
  }

  if (filters.account_id) {
    params.account_id = filters.account_id;
  }

  if (filters.submitter) {
    params.submitter = filters.submitter;
  }

  if (filters.select.length > 0) {
    params.select = filters.select;
  }

  const builderFilters = sanitizeReportBuilderFilters(filters.filters);

  if (builderFilters.length > 0) {
    params.filters = builderFilters;
  }

  if (filters.sort?.column) {
    params.sort = filters.sort;
  }

  return params;
};

export const shouldHandleAsyncExportPayload = (responseStatus: number, contentType: string | null): boolean => {
  if (responseStatus === 202) {
    return true;
  }

  return Boolean(contentType && contentType.includes("application/json"));
};
