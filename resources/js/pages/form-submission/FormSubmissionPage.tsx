import React, { useState } from "react";
import { Link, router } from "@inertiajs/react";
import { AlertCircle, ChevronLeft } from "lucide-react";
import { toast } from "sonner";

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
import { useFormSubmissionState } from "./hooks/useFormSubmissionState";
import type { FormPayload } from "@/types/form";

import logoUrl from "@/assets/auf_logo.png";

interface Props {
  form: FormPayload;
  submitRouteName: string;
  backRouteName: string;
  userFullName?: string | null;
}

const FormSubmissionPage: React.FC<Props> = ({ form, submitRouteName, backRouteName, userFullName }) => {
  const { state, setters, derived, actions } = useFormSubmissionState(form, userFullName);

  const {
    values,
    attachments,
    facilities,
    submitting,
    confirmOpen,
    slotDraftsByField,
    dtSlotsByField,
    plainTempByField,
    plainDatesByField,
    rangeDraftsByField,
    dateRangesByField,
    unavailableSlotsByField,
    currentDateByField,
    allSlots,
    allPlainDates,
    allDateRanges,
    unavailableDates,
  } = state;

  const availability = form.submission_availability;
  const isSubmitDisabled = submitting || !availability?.can_submit;
  const disabledReason = availability?.message ?? null;

  const {
    setSubmitting,
    setConfirmOpen,
    setSlotDraftDate,
    setSlotDraftStart,
    setSlotDraftEnd,
    setSlotDraftFacility,
    setPlainTempDate,
    setRangeStart,
    setRangeEnd,
    setCurrentDate,
  } = setters;

  const {
    visibleFields,
    plainDateFields,
    dateTimeFields,
    rangeFields,
    timeSlots,
    getCalendarDays,
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
    prepareSubmission,
    resetAfterSubmit,
    isTimeDisabled,
  } = actions;

  const [mode, setMode] = useState<"preview" | "filling">("preview");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const handleSubmit = () => {
    if (isSubmitDisabled) return;

    // Trigger HTML5 validation
    const formElement = document.getElementById('request-form') as HTMLFormElement;
    if (formElement && !formElement.checkValidity()) {
      formElement.reportValidity();
      return;
    }

    setFieldErrors({});
    setSubmitting(true);
    const formData = prepareSubmission();
    formData.append("client_timestamp", new Date().toISOString());

    router.post(route(submitRouteName, { id: form.id }), formData, {
      forceFormData: true,
      preserveScroll: true,
      onError: (errors) => {
        const errorMap = errors as Record<string, string>;
        setFieldErrors(errorMap);
        const errorKeys = Object.keys(errorMap);
        const count = errorKeys.length;
        toast.error("Please review the form", {
          description: `${count} field${count !== 1 ? "s" : ""} need${count === 1 ? "s" : ""} attention.`,
        });
        if (errorKeys[0]) {
          setTimeout(() => document.getElementById(`field-${errorKeys[0]}`)?.focus(), 0);
        }
      },
      onFinish: () => {
        resetAfterSubmit();
      },
    });
  };

  const nonDateFields = visibleFields.filter((field) => field.data_type !== "date");
  const visibleForm = { ...form, fields: visibleFields };

  return (
    <AppLayout>
      <div className="mx-auto w-full max-w-[1300px] px-3 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-8">
        <Link
          href={route(backRouteName)}
          className="group mb-4 sm:mb-6 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 motion-safe:transition-colors"
        >
          <ChevronLeft className="h-4 w-4 motion-safe:transition-transform motion-safe:group-hover:-translate-x-0.5" />
          Back to Forms
        </Link>

        <PaperFormShell
          orgName="Angeles University Foundation"
          systemName="Digital Document Management System"
          logoSrc={logoUrl}
          className="space-y-5 sm:space-y-6"
          actions={
            mode === "preview" ? (
              <Button
                type="button"
                onClick={() => setMode("filling")}
                className="h-10 sm:h-11 w-full"
              >
                Start filling out this form
              </Button>
            ) : (
              <Button
                type="button"
                onClick={() => setConfirmOpen(true)}
                className="h-10 sm:h-11 w-full"
                disabled={isSubmitDisabled}
              >
                {!availability?.can_submit
                  ? "Submissions Closed"
                  : submitting
                  ? "Submitting…"
                  : "Submit Request"}
              </Button>
            )
          }
        >
          {disabledReason ? (
            <Alert className="border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
              <AlertCircle className="h-4 w-4 text-gray-700 dark:text-gray-300" />
              <AlertDescription className="text-gray-700 dark:text-gray-300">{disabledReason}</AlertDescription>
            </Alert>
          ) : null}

          <div className="space-y-2">
            <h1 className="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100">{form.form_name}</h1>
            {form.description && (
              <p className="text-sm text-gray-500 dark:text-gray-400">{form.description}</p>
            )}
          </div>

          {mode === "preview" ? (
            <div className="space-y-3">
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Review the fields in this form before you begin.
              </p>
              <ul className="rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden">
                {form.fields
                  .filter((f) => !["section", "heading", "image"].includes(f.data_type))
                  .map((field) => (
                    <li
                      key={field.id}
                      className="flex items-center justify-between px-4 py-2.5 text-sm"
                    >
                      <span className="font-medium text-gray-900 dark:text-gray-100">{field.label}</span>
                      {field.is_required ? (
                        <span className="text-xs font-medium text-red-600 dark:text-red-400">Required</span>
                      ) : (
                        <span className="text-xs text-gray-500 dark:text-gray-400">Optional</span>
                      )}
                    </li>
                  ))}
              </ul>
            </div>
          ) : (
          <form id="request-form" onSubmit={(event) => event.preventDefault()} className="space-y-6 sm:space-y-8">
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

            {dateTimeFields.map((field) => {
              const fieldName = field.field_name;
              const slotDraft = slotDraftsByField[fieldName] ?? {
                date: undefined,
                start: "",
                end: "",
                facility: "",
              };

              return (
                <DateTimeSection
                  key={`slot-${fieldName}`}
                  title={field.label?.trim() || `Date & Time${field.require_facility ? " (with Facility)" : ""}`}
                  requireFacility={Boolean(field.require_facility)}
                  facilities={facilities}
                  dtTempDate={slotDraft.date}
                  dtTempStart={slotDraft.start}
                  dtTempEnd={slotDraft.end}
                  dtTempFacility={slotDraft.facility}
                  dtSlots={dtSlotsByField[fieldName] ?? []}
                  timeSlots={timeSlots}
                  calendarDays={getCalendarDays(fieldName)}
                  unavailableSlots={unavailableSlotsByField[fieldName] ?? []}
                  currentDate={currentDateByField[fieldName] ?? new Date()}
                  canGoPrev={canGoPrev(fieldName)}
                  setDtTempDate={(date) => setSlotDraftDate(fieldName, date)}
                  setDtTempStart={(value) => setSlotDraftStart(fieldName, value)}
                  setDtTempEnd={(value) => setSlotDraftEnd(fieldName, value)}
                  setDtTempFacility={(value) => setSlotDraftFacility(fieldName, value)}
                  addSlot={() => addDtSlot(fieldName, Boolean(field.require_facility))}
                  removeSlot={(index) => removeDtSlot(fieldName, index)}
                  setCurrentDate={(date) => setCurrentDate(fieldName, date)}
                  isTimeDisabled={(time) => isTimeDisabled(fieldName, time)}
                />
              );
            })}

            {plainDateFields.map((field) => {
              const fieldName = field.field_name;

              return (
                <PlainDateSection
                  key={`plain-${fieldName}`}
                  title={field.label?.trim() || "Date Selection"}
                  plainTempDate={plainTempByField[fieldName]}
                  setPlainTempDate={(date) => setPlainTempDate(fieldName, date)}
                  unavailableDates={unavailableDates}
                  plainDates={plainDatesByField[fieldName] ?? []}
                  addPlainDate={() => addPlainDate(fieldName)}
                  removePlainDate={(index) => removePlainDate(fieldName, index)}
                />
              );
            })}

            {rangeFields.map((field) => {
              const fieldName = field.field_name;
              const draft = rangeDraftsByField[fieldName] ?? {};

              return (
                <DateRangeSection
                  key={`range-${fieldName}`}
                  title={field.label?.trim() || "Date Range Selection"}
                  rangeStart={draft.start}
                  rangeEnd={draft.end}
                  setRangeStart={(date) => setRangeStart(fieldName, date)}
                  setRangeEnd={(date) => setRangeEnd(fieldName, date)}
                  addDateRange={() => addDateRange(fieldName)}
                  removeDateRange={(index) => removeDateRange(fieldName, index)}
                  dateRanges={dateRangesByField[fieldName] ?? []}
                />
              );
            })}

            <AttachmentsSection
              attachments={attachments}
              onUpload={handleFileUpload}
              onRemove={removeAttachment}
            />
          </form>
          )}

        </PaperFormShell>
      </div>

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        onConfirm={handleSubmit}
        loading={submitting}
      >
        <SubmissionSummary
          form={visibleForm}
          values={values}
          slots={allSlots}
          plainDates={allPlainDates}
          dateRanges={allDateRanges}
          attachments={attachments}
          requireFacility={dateTimeFields.some((field) => Boolean(field.require_facility))}
          facilities={facilities}
        />
      </ConfirmDialog>
    </AppLayout>
  );
};

export default FormSubmissionPage;
