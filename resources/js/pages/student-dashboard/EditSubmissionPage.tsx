import React, { useState } from "react";
import { router, usePage, Link } from "@inertiajs/react";
import { toast } from "sonner";
import { Info, ChevronLeft } from "lucide-react";

import AppLayout from "@/layouts/app-layout";
import PaperFormShell from "@/components/forms/PaperFormShell";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription } from "@/components/ui/alert";

import ConfirmDialog from "@/pages/Forms/components/ConfirmDialog";
import { BasicInfoSection } from "@/pages/Forms/components/sections/BasicInfoSection";
import { DateTimeSection } from "@/pages/Forms/components/sections/DateTimeSection";
import { PlainDateSection } from "@/pages/Forms/components/sections/PlainDateSection";
import { DateRangeSection } from "@/pages/Forms/components/sections/DateRangeSection";
import { AttachmentsSection } from "@/pages/Forms/components/sections/AttachmentsSection";
import { SubmissionSummary } from "@/pages/Forms/components/summary/SubmissionSummary";

import { useEditSubmissionState } from "./hooks/useEditSubmissionState";
import type { SubmissionEditPayload } from "./hooks/useEditSubmissionState";

import logoUrl from "@/assets/auf_logo.png";

export default function EditSubmissionPage() {
  const { props } = usePage<{ submission: SubmissionEditPayload }>();
  const submission = props.submission;
  const updateRouteName = submission.update_route_name ?? "student-dashboard.submission.update";
  const backHref = submission.back_href ?? "/student-dashboard";

  const { state, setters, derived, actions } = useEditSubmissionState(submission);

  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const {
    values,
    attachments,
    existingAttachments,
    dtTempDate,
    dtTempStart,
    dtTempEnd,
    dtTempFacility,
    dtSlots,
    plainDates,
    dateRanges,
    plainTempDate,
    rangeStart,
    rangeEnd,
    facilities,
    unavailableSlots,
    calendarDays,
    timeSlots,
    currentDate,
    submitting,
    confirmOpen,
  } = state;

  const {
    setDtTempDate,
    setDtTempStart,
    setDtTempEnd,
    setDtTempFacility,
    setPlainTempDate,
    setRangeStart,
    setRangeEnd,
    setCurrentDate,
    setSubmitting,
    setConfirmOpen,
  } = setters;

  const {
    hasSlots,
    hasPlainDate,
    hasRange,
    requireFacility,
    dateTimeField,
    plainDateField,
    rangeField,
    canGoPrev,
  } = derived;

  const {
    handleSimpleChange,
    handleMetaCheckboxToggle,
    handleMetaCheckboxQty,
    handleMetaCheckboxText,
    handleMetaSinglePick,
    handleMetaSingleQty,
    handleMetaSingleText,
    addDtSlot,
    removeDtSlot,
    addPlainDate,
    removePlainDate,
    addDateRange,
    removeDateRange,
    handleFileUpload,
    removeAttachment,
    removeExistingAttachment,
    prepareSubmission,
    resetAfterSubmit,
    isTimeDisabled,
  } = actions;

  const nonDateFields = submission.form_fields.filter((field) => field.data_type !== "date");

  const handleSubmit = () => {
    if (submitting) return;

    // Trigger HTML5 validation
    const formElement = document.getElementById('edit-submission-form') as HTMLFormElement;
    if (formElement && !formElement.checkValidity()) {
      formElement.reportValidity();
      return;
    }

    setFieldErrors({});
    setSubmitting(true);
    const formData = prepareSubmission();
    formData.append("_method", "PUT");

    router.post(
      route(updateRouteName, {
        formId: submission.form_id,
        submissionId: submission.id,
      }),
      formData,
      {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
          const errorMap = errors as Record<string, string>;
          setFieldErrors(errorMap);
          const errorKeys = Object.keys(errorMap);
          const count = errorKeys.length;
          toast.error("Please review your submission", {
            description: `${count} field${count !== 1 ? "s" : ""} need${count === 1 ? "s" : ""} attention.`,
          });
          if (errorKeys[0]) {
            setTimeout(() => document.getElementById(`field-${errorKeys[0]}`)?.focus(), 0);
          }
        },
        onFinish: () => {
          resetAfterSubmit();
        },
      }
    );
  };

  const dateTimeTitle =
    (dateTimeField?.label?.trim() || null) ??
    `Date & Time${requireFacility ? " (with Facility)" : ""}`;
  const plainDateTitle = (plainDateField?.label?.trim() || null) ?? "Date Selection";
  const rangeTitle = (rangeField?.label?.trim() || null) ?? "Date Range Selection";

  return (
    <AppLayout>
      <div className="mx-auto w-full max-w-[1300px] px-3 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-8">
        {/* Back navigation */}
        <div className="mb-4 sm:mb-6">
          <Link
            href={backHref}
            className="group inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 motion-safe:transition-colors"
          >
            <ChevronLeft className="h-4 w-4 motion-safe:transition-transform motion-safe:group-hover:-translate-x-0.5" />
            Back to Dashboard
          </Link>
        </div>
        <PaperFormShell
          orgName="Angeles University Foundation"
          systemName="Digital Document Management System"
          logoSrc={logoUrl}
          className="space-y-5 sm:space-y-6"
          actions={
            <Button
              type="button"
              onClick={() => setConfirmOpen(true)}
              className="h-11 w-full bg-primary text-primary-foreground hover:bg-primary/90"
              disabled={submitting}
            >
              {submitting ? "Saving…" : "Save Changes"}
            </Button>
          }
        >
          <div className="space-y-3">
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">{submission.form_name}</h1>
            {submission.description && (
              <p className="text-sm text-gray-500 dark:text-gray-400">{submission.description}</p>
            )}
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Update your submission. Once saved, the request will return to the approval queue.
            </p>
          </div>

          <form id="edit-submission-form" onSubmit={(event) => event.preventDefault()} className="space-y-8">
            <BasicInfoSection
              fields={nonDateFields}
              values={values}
              onSimpleChange={handleSimpleChange}
              onMetaCheckboxToggle={handleMetaCheckboxToggle}
              onMetaCheckboxQty={handleMetaCheckboxQty}
              onMetaCheckboxText={handleMetaCheckboxText}
              onMetaSinglePick={handleMetaSinglePick}
              onMetaSingleQty={handleMetaSingleQty}
              onMetaSingleText={handleMetaSingleText}
              fieldErrors={fieldErrors}
            />

            {hasSlots && (
              <>
                <Alert className="border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400">
                  <Info className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                  <AlertDescription>
                    Tip: Edit existing slots or add new ones below. Removing a slot will delete it
                    from your submission.
                  </AlertDescription>
                </Alert>
                <DateTimeSection
                  title={dateTimeTitle}
                  requireFacility={requireFacility}
                  facilities={facilities}
                  dtTempDate={dtTempDate}
                  dtTempStart={dtTempStart}
                  dtTempEnd={dtTempEnd}
                  dtTempFacility={dtTempFacility}
                  dtSlots={dtSlots}
                  timeSlots={timeSlots}
                  calendarDays={calendarDays}
                  unavailableSlots={unavailableSlots}
                  currentDate={currentDate}
                  canGoPrev={canGoPrev}
                  setDtTempDate={setDtTempDate}
                  setDtTempStart={setDtTempStart}
                  setDtTempEnd={setDtTempEnd}
                  setDtTempFacility={setDtTempFacility}
                  addSlot={addDtSlot}
                  removeSlot={removeDtSlot}
                  setCurrentDate={setCurrentDate}
                  isTimeDisabled={isTimeDisabled}
                />
              </>
            )}

            {hasPlainDate && (
              <PlainDateSection
                title={plainDateTitle}
                plainTempDate={plainTempDate}
                setPlainTempDate={setPlainTempDate}
                unavailableDates={[]}
                plainDates={plainDates}
                addPlainDate={addPlainDate}
                removePlainDate={removePlainDate}
              />
            )}

            {hasRange && (
              <DateRangeSection
                title={rangeTitle}
                rangeStart={rangeStart}
                rangeEnd={rangeEnd}
                setRangeStart={setRangeStart}
                setRangeEnd={setRangeEnd}
                addDateRange={addDateRange}
                removeDateRange={removeDateRange}
                dateRanges={dateRanges}
              />
            )}

            <AttachmentsSection
              attachments={attachments}
              existingAttachments={existingAttachments}
              onUpload={handleFileUpload}
              onRemove={removeAttachment}
              onRemoveExisting={removeExistingAttachment}
              title="Attachments"
              description="Upload supporting documents or remove files you previously attached."
              helperText="Changes to attachments will be saved when you submit this update."
            />
          </form>
        </PaperFormShell>
      </div>

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        onConfirm={handleSubmit}
        loading={submitting}
        confirmLabel="Save Changes"
      >
        <SubmissionSummary
          form={{
            id: submission.form_id,
            form_name: submission.form_name,
            description: submission.description,
            fields: submission.form_fields,
          }}
          values={values}
          slots={dtSlots}
          plainDates={plainDates}
          dateRanges={dateRanges}
          attachments={attachments}
          existingAttachments={existingAttachments}
          requireFacility={requireFacility}
          facilities={facilities}
        />
      </ConfirmDialog>
    </AppLayout>
  );
}
