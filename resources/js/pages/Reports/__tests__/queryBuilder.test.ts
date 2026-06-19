import { describe, expect, test } from "vitest";
import {
  buildAttachmentMap,
  buildReportQueryParams,
  shouldIndexAttachments,
  shouldHandleAsyncExportPayload,
} from "../queryBuilder";
import { Attachment, ReportSubmission } from "../types";
import { ReportFiltersState } from "../types";

describe("reports query builder utilities", () => {
  const baseFilters: ReportFiltersState = {
    form_id: 12,
    date_from: null,
    date_to: null,
    submission_status: "",
    account_id: null,
    submitter: null,
    select: ["id", "submission_status"],
    filters: [],
    sort: null,
    per_page: 25,
    page: 1,
  };

  test("buildReportQueryParams includes select, valid filters, and sort payload", () => {
    const params = buildReportQueryParams({
      ...baseFilters,
      filters: [
        { column: "submission_status", operator: "eq", value: "approved" },
        { column: "", operator: "eq", value: "ignored" },
      ],
      sort: {
        column: "created_at",
        direction: "desc",
      },
    });

    expect(params.form_id).toBe(12);
    expect(params.select).toEqual(["id", "submission_status"]);
    expect(params.filters).toEqual([
      { column: "submission_status", operator: "eq", value: "approved" },
    ]);
    expect(params.sort).toEqual({ column: "created_at", direction: "desc" });
  });

  test("buildReportQueryParams keeps nullary operators with null values", () => {
    const params = buildReportQueryParams({
      ...baseFilters,
      filters: [
        { column: "workflow_status", operator: "is_null", value: "" },
      ],
    });

    expect(params.filters).toEqual([
      { column: "workflow_status", operator: "is_null", value: null },
    ]);
  });

  test("shouldHandleAsyncExportPayload detects async JSON responses", () => {
    expect(shouldHandleAsyncExportPayload(202, "application/json")).toBe(true);
    expect(shouldHandleAsyncExportPayload(200, "application/json; charset=UTF-8")).toBe(true);
    expect(shouldHandleAsyncExportPayload(200, "text/csv; charset=UTF-8")).toBe(false);
  });

  test("buildAttachmentMap tolerates projected rows without attachments", () => {
    const projectedRows: Array<Pick<ReportSubmission, "id" | "submitter_name"> & { attachments?: Attachment[] }> = [
      {
        id: 1,
        submitter_name: "Projection Only",
      },
      {
        id: 2,
        submitter_name: "With Attachment",
        attachments: [
          {
            id: 99,
            original_name: "proof.pdf",
            file_path: "exports/proof.pdf",
            mime_type: "application/pdf",
            uploaded_by: 1,
            is_image: false,
            is_pdf: true,
          },
        ],
      },
    ];

    const attachmentMap = buildAttachmentMap([
      ...projectedRows,
    ]);

    expect(attachmentMap.size).toBe(1);
    expect(attachmentMap.get(99)?.original_name).toBe("proof.pdf");
  });

  test("shouldIndexAttachments is false when attachments column is not selected", () => {
    expect(shouldIndexAttachments(["id", "submitter_name", "email"])).toBe(false);
    expect(shouldIndexAttachments(["id", "attachments"])).toBe(true);
  });
});
