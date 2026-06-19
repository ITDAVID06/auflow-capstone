import React from "react";
import { Link } from "@inertiajs/react";
import { Eye, ChevronRight } from "lucide-react";
import type { Row } from "./AdminRequestCards";

interface CompactSubmissionRowProps {
  submission: Row;
}

export default function CompactSubmissionRow({ submission }: CompactSubmissionRowProps) {
  const formatDate = (dateString?: string | null) => {
    if (!dateString) return "—";
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  };

  // Status color mapping for left border
  const getStatusBorderColor = (status: string) => {
    const s = status.toLowerCase();
    if (s === "rejected") return "border-l-red-500";
    if (s === "approved" || s === "completed") return "border-l-green-500";
    if (s === "pending") return "border-l-amber-500";
    return "border-l-gray-300";
  };

  const currentStep = submission.workflow_preview.current || "—";
  const totalSteps = submission.workflow_preview.count;
  const completedSteps = submission.workflow_preview.completed;

  return (
    <div
      className={`bg-white rounded-lg border border-gray-200 border-l-4 ${getStatusBorderColor(
        submission.status
      )} hover:bg-gray-50 transition-colors p-4`}
    >
      <div className="flex items-center justify-between gap-4">
        {/* Left: Form Name + Submitter */}
        <div className="flex-1 min-w-0">
          <h3 className="font-semibold text-base text-gray-900 mb-1 truncate">
            {submission.form_name}
          </h3>
          <p className="text-sm text-gray-500 truncate">
            Submitted by {submission.submitter}
          </p>
        </div>

        {/* Middle: Date + Step Progress */}
        <div className="flex items-center gap-8 text-sm">
          <div className="text-center">
            <p className="text-gray-500 text-xs mb-0.5">Date</p>
            <p className="font-medium text-gray-900">
              {formatDate(submission.submitted_at)}
            </p>
          </div>
          <div className="text-center">
            <p className="text-gray-500 text-xs mb-0.5">Progress</p>
            <p className="font-medium text-gray-900">
              Step {completedSteps} of {totalSteps}
            </p>
          </div>
          <div className="text-center max-w-[180px]">
            <p className="text-gray-500 text-xs mb-0.5">Current</p>
            <p className="font-medium text-gray-900 truncate" title={currentStep}>
              {currentStep}
            </p>
          </div>
        </div>

        {/* Right: View Link */}
        <Link
          href={route("admin-submissions.show", {
            formId: submission.form_id,
            submissionId: submission.submission_id,
          })}
          className="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 touch-manipulation"
          aria-label={`View submission from ${submission.submitter}`}
        >
          <Eye className="w-4 h-4" aria-hidden="true" />
          View
          <ChevronRight className="w-4 h-4" aria-hidden="true" />
        </Link>
      </div>
    </div>
  );
}
