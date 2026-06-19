import React, { ChangeEvent } from "react";
import { FileText, Plus, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { ExistingAttachment } from "@/types/form";

interface AttachmentsSectionProps {
  attachments: File[];
  existingAttachments?: ExistingAttachment[];
  onUpload: (files: FileList | null) => void;
  onRemove: (index: number) => void;
  onRemoveExisting?: (index: number) => void;
  title?: string;
  description?: string;
  helperText?: string;
}

export const AttachmentsSection: React.FC<AttachmentsSectionProps> = ({
  attachments,
  existingAttachments,
  onUpload,
  onRemove,
  onRemoveExisting,
  title = "Attachments (Optional)",
  description = "Upload supporting documents. You can select multiple files at once.",
  helperText = "Files are not uploaded until you submit this request.",
}) => {
  const inputId = "request-form-file-upload";
  const hasExisting = (existingAttachments?.length ?? 0) > 0;
  const hasNew = attachments.length > 0;
  const showEmptyState = !hasExisting && !hasNew;

  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    onUpload(event.target.files);
    event.target.value = "";
  };

  return (
    <section className="pt-6">
      <div className="pb-4">
        <h2 className="text-base font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
        <p className="text-sm text-gray-500 dark:text-gray-400">{description}</p>
        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
          Accepted formats: JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX (Max 10MB per file)
        </p>
      </div>

      <div className="space-y-4">
        <div className="flex flex-col gap-2">
          <Label
            htmlFor={inputId}
            className="inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2.5 text-sm font-medium text-white motion-safe:transition-colors hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-700 sm:w-auto cursor-pointer"
          >
            <Plus className="h-4 w-4" />
            Choose Files to Upload
          </Label>
          <Input
            id={inputId}
            type="file"
            multiple
            accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx"
            onChange={handleChange}
            className="hidden"
          />
          <p className="text-xs text-gray-500 dark:text-gray-400">
            {helperText}
          </p>
        </div>

        {showEmptyState ? (
          <div className="rounded-lg border border-gray-200 dark:border-gray-700 p-6 text-center">
            <FileText className="mx-auto mb-3 h-8 w-8 text-gray-400 dark:text-gray-500" />
            <p className="text-sm text-gray-500 dark:text-gray-400">No attachments yet</p>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Click &ldquo;Choose Files&rdquo; above to add documents
            </p>
          </div>
        ) : (
          <div className="space-y-2">
            {hasExisting && (
              <>
                <p className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                  Current Attachments
                </p>
                {existingAttachments!.map((file, index) => (
                  <div
                    key={`${file.id}-${index}`}
                    className="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-sm"
                  >
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                      <FileText className="h-5 w-5 flex-shrink-0 text-gray-400 dark:text-gray-500" />
                      <div className="min-w-0 flex-1">
                        <p className="truncate font-medium text-gray-900 dark:text-gray-100">{file.original_name}</p>
                        {file.mime_type && (
                          <p className="text-xs text-gray-500 dark:text-gray-400">{file.mime_type}</p>
                        )}
                      </div>
                    </div>
                    {onRemoveExisting && (
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => onRemoveExisting(index)}
                        className="ml-2 hover:bg-red-100 dark:hover:bg-red-950"
                        aria-label={`Remove existing attachment: ${file.original_name}`}
                      >
                        <X className="h-4 w-4 text-red-600" aria-hidden="true" />
                      </Button>
                    )}
                  </div>
                ))}
              </>
            )}

            {hasNew && (
              <>
                {hasExisting && (
                  <p className="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Files to Upload
                  </p>
                )}
                {!hasExisting && (
                  <p className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Files to Upload
                  </p>
                )}
                {attachments.map((file, index) => (
                  <div
                    key={`${file.name}-${file.size}-${index}`}
                    className="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-sm"
                  >
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                      <FileText className="h-5 w-5 flex-shrink-0 text-gray-400 dark:text-gray-500" />
                      <div className="min-w-0 flex-1">
                        <p className="truncate font-medium text-gray-900 dark:text-gray-100">{file.name}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                          {(file.size / 1024 / 1024).toFixed(2)} MB
                        </p>
                      </div>
                    </div>
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      onClick={() => onRemove(index)}
                      className="ml-2 hover:bg-red-100 dark:hover:bg-red-950"
                      aria-label={`Remove attachment: ${file.name}`}
                    >
                      <X className="h-4 w-4 text-red-600" aria-hidden="true" />
                    </Button>
                  </div>
                ))}
              </>
            )}
          </div>
        )}
      </div>
    </section>
  );
};
