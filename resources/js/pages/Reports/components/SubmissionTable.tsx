import React, { useState } from "react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ChevronDown, ChevronRight, Download, Eye, FileIcon, ImageIcon } from "lucide-react";
import { Attachment, ReportColumn, ReportPagination, ReportSubmission } from "../types";

interface SubmissionTableProps {
  columns: ReportColumn[];
  submissions: ReportSubmission[];
  pagination: ReportPagination;
  onPageChange: (page: number) => void;
  loading?: boolean;
}

const AttachmentRow: React.FC<{ attachments: Attachment[] }> = ({ attachments }) => {
  if (attachments.length === 0) return null;
  return (
    <div className="flex flex-wrap gap-2 py-2 px-4 bg-muted/40 rounded">
      {attachments.map((a) => (
        <div
          key={a.id}
          className="flex items-center gap-2 text-sm border rounded px-2 py-1 bg-background"
        >
          {a.is_image ? (
            <ImageIcon className="h-4 w-4 text-muted-foreground" />
          ) : (
            <FileIcon className="h-4 w-4 text-muted-foreground" />
          )}
          <span className="max-w-[160px] truncate">{a.original_name}</span>
          <a
            href={route("reports.attachments.preview", a.id)}
            target="_blank"
            rel="noopener noreferrer"
            className="text-muted-foreground hover:text-foreground"
            aria-label={`Preview ${a.original_name}`}
          >
            <Eye className="h-4 w-4" />
          </a>
          <a
            href={route("reports.attachments.download", a.id)}
            className="text-muted-foreground hover:text-foreground"
            aria-label={`Download ${a.original_name}`}
          >
            <Download className="h-4 w-4" />
          </a>
        </div>
      ))}
    </div>
  );
};

export const SubmissionTable: React.FC<SubmissionTableProps> = ({
  columns,
  submissions,
  pagination,
  onPageChange,
  loading = false,
}) => {
  const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());

  const toggleExpand = (id: number) => {
    setExpandedIds((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };

  return (
    <div className="space-y-3">
      <div className="rounded-md border overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-8" />
              {columns.map((col) => (
                <TableHead key={col.key}>{col.label}</TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <TableRow key={i}>
                  <TableCell colSpan={columns.length + 1}>
                    <div className="h-4 w-full rounded bg-muted animate-pulse" />
                  </TableCell>
                </TableRow>
              ))
            ) : submissions.length === 0 ? (
              <TableRow>
                <TableCell colSpan={columns.length + 1} className="text-center py-8 text-muted-foreground">
                  No submissions found.
                </TableCell>
              </TableRow>
            ) : (
              submissions.map((row) => {
                const isExpanded = expandedIds.has(row.id);
                return (
                  <React.Fragment key={row.id}>
                    <TableRow className="group">
                      <TableCell className="pr-0">
                        {row.attachment_count > 0 && (
                          <button
                            onClick={() => toggleExpand(row.id)}
                            className="text-muted-foreground hover:text-foreground"
                            aria-label={isExpanded ? "Collapse attachments" : "Expand attachments"}
                          >
                            {isExpanded ? (
                              <ChevronDown className="h-4 w-4" />
                            ) : (
                              <ChevronRight className="h-4 w-4" />
                            )}
                          </button>
                        )}
                      </TableCell>
                      {columns.map((col) => (
                        <TableCell key={col.key}>
                          {col.key === "submission_status" ? (
                            <Badge variant="outline">
                              {String(row[col.key] ?? "—")}
                            </Badge>
                          ) : (
                            String(row[col.key] ?? "—")
                          )}
                        </TableCell>
                      ))}
                    </TableRow>
                    {isExpanded && row.attachments?.length > 0 && (
                      <TableRow>
                        <TableCell colSpan={columns.length + 1} className="p-0">
                          <AttachmentRow attachments={row.attachments} />
                        </TableCell>
                      </TableRow>
                    )}
                  </React.Fragment>
                );
              })
            )}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      {pagination.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">
            Page {pagination.current_page} of {pagination.last_page} — {pagination.total} total
          </span>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page <= 1}
              onClick={() => onPageChange(pagination.current_page - 1)}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() => onPageChange(pagination.current_page + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  );
};
