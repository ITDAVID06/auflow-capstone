import { useCallback, useEffect, useMemo, useState } from "react";
import axios from "axios";
import {
  addDays,
  addMonths,
  eachDayOfInterval,
  endOfMonth,
  format,
  getDay,
  isBefore,
  startOfMonth,
  startOfToday,
  subDays,
} from "date-fns";

import { buildFormData } from "@/components/forms/useFormData";

import {
  ACCEPT_MIME,
  MAX_FILE_BYTES,
  hasAllowedExtension,
  isDuplicateFile,
} from "../../Forms/utils/fileValidation";
import { encodeMetaForSubmit, normalizeMetaOption } from "../../Forms/utils/meta";
import { parseFieldValue } from "../../Forms/utils/parse";
import type {
  ExistingAttachment,
  FormField,
  OptionMeta,
  MultiMetaSelection,
  SelectedSlot,
  SingleMetaSelection,
} from "@/types/form";

type SubmissionSlotInput = {
  date: string;
  start_time?: string | null;
  end_time?: string | null;
  facility_id?: string | number | null;
};

export type SubmissionEditPayload = {
  id: number;
  form_id: number;
  form_name: string;
  description?: string;
  update_route_name?: string;
  back_href?: string;
  form_fields: FormField[];
  fields: Record<string, any>;
  attachments: ExistingAttachment[];
  slots: SubmissionSlotInput[];
  date_ranges?: Array<{ from?: string; to?: string; start?: string; end?: string; start_date?: string; end_date?: string }>;
};

const generateTimeSlots = () => {
  const slots: string[] = [];
  for (let hour = 6; hour <= 22; hour++) {
    for (let minute = 0; minute < 60; minute += 30) {
      slots.push(`${hour.toString().padStart(2, "0")}:${minute.toString().padStart(2, "0")}`);
    }
  }
  return slots;
};

export const useEditSubmissionState = (submission: SubmissionEditPayload) => {
  const initialRanges = useMemo(() => {
    const ranges: Array<{ from: Date; to: Date }> = [];

    // Load from submission.date_ranges (backend provides this)
    if (submission.date_ranges && Array.isArray(submission.date_ranges)) {
      submission.date_ranges.forEach((item) => {
        const startKey = item.from ?? item.start ?? item.start_date;
        const endKey = item.to ?? item.end ?? item.end_date;
        
        if (startKey && endKey) {
          ranges.push({
            from: new Date(startKey),
            to: new Date(endKey),
          });
        }
      });
    }

    return ranges;
  }, [submission.date_ranges]);

  const [values, setValues] = useState<Record<string, any>>(() => {
    const initial: Record<string, any> = {};
    submission.form_fields.forEach((field) => {
      const raw = submission.fields[field.field_name];
      initial[field.field_name] = parseFieldValue(raw, field);
    });
    return initial;
  });

  const [attachments, setAttachments] = useState<File[]>([]);
  const [existingAttachments, setExistingAttachments] = useState<ExistingAttachment[]>(
    submission.attachments ?? []
  );

  const [dtSlots, setDtSlots] = useState<SelectedSlot[]>(() =>
    submission.slots
      .filter((slot) => slot.start_time || slot.end_time || slot.facility_id)
      .map((slot) => ({
        date: new Date(slot.date),
        start_time: slot.start_time ?? undefined,
        end_time: slot.end_time ?? undefined,
        facility_id: slot.facility_id ? String(slot.facility_id) : undefined,
      }))
  );

  const [plainDates, setPlainDates] = useState<Date[]>(() =>
    submission.slots
      .filter((slot) => !slot.start_time && !slot.end_time && !slot.facility_id)
      .map((slot) => new Date(slot.date))
  );

  const [dateRanges, setDateRanges] = useState<Array<{ from: Date; to: Date }>>(initialRanges);

  const [dtTempDate, setDtTempDate] = useState<Date>();
  const [dtTempStart, setDtTempStart] = useState("");
  const [dtTempEnd, setDtTempEnd] = useState("");
  const [dtTempFacility, setDtTempFacility] = useState("");

  const [rangeStart, setRangeStart] = useState<Date>();
  const [rangeEnd, setRangeEnd] = useState<Date>();
  const [plainTempDate, setPlainTempDate] = useState<Date>();

  const [currentDate, setCurrentDate] = useState<Date>(new Date());
  const [facilities, setFacilities] = useState<Array<{ id: number; name: string }>>([]);
  const [unavailableSlots, setUnavailableSlots] = useState<
    Array<{ date: string; start_time?: string; end_time?: string; facility_id?: number | null }>
  >([]);

  const [submitting, setSubmitting] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);

  const hasSlots = useMemo(
    () => submission.form_fields.some((field) => field.data_type === "date" && field.use_slots),
    [submission.form_fields]
  );

  const requireFacility = useMemo(
    () =>
      submission.form_fields.some(
        (field) => field.data_type === "date" && field.use_slots && field.require_facility
      ),
    [submission.form_fields]
  );

  const hasPlainDate = useMemo(
    () =>
      submission.form_fields.some(
        (field) =>
          field.data_type === "date" &&
          !field.use_slots &&
          !field.require_facility &&
          (field.date_mode ?? "single") === "single"
      ),
    [submission.form_fields]
  );

  const hasRange = useMemo(
    () =>
      submission.form_fields.some(
        (field) =>
          field.data_type === "date" &&
          !field.use_slots &&
          (field.date_mode ?? "single") === "range"
      ),
    [submission.form_fields]
  );

  const dateTimeField = useMemo(
    () => submission.form_fields.find((field) => field.data_type === "date" && field.use_slots),
    [submission.form_fields]
  );

  const plainDateField = useMemo(
    () =>
      submission.form_fields.find(
        (field) =>
          field.data_type === "date" &&
          !field.use_slots &&
          !field.require_facility &&
          (field.date_mode ?? "single") === "single"
      ),
    [submission.form_fields]
  );

  const rangeField = useMemo(
    () =>
      submission.form_fields.find(
        (field) =>
          field.data_type === "date" &&
          !field.use_slots &&
          (field.date_mode ?? "single") === "range"
      ),
    [submission.form_fields]
  );

  const timeSlots = useMemo(() => generateTimeSlots(), []);

  useEffect(() => {
    axios.get("/admin/facilities/active").then((res) => {
      setFacilities(Array.isArray(res.data) ? res.data : []);
    });
  }, []);

  useEffect(() => {
    if (!dtTempDate || !hasSlots) {
      setUnavailableSlots([]);
      return;
    }

    if (isBefore(dtTempDate, startOfToday())) {
      setUnavailableSlots([]);
      return;
    }

    const params = new URLSearchParams({ date: format(dtTempDate, "yyyy-MM-dd") });

    if (requireFacility && dtTempFacility) {
      params.append("facility_id", dtTempFacility);
    }

    axios
      .get(`/admin/facilities/slots/availability?${params.toString()}`)
      .then((res) => {
        const data = Array.isArray(res.data) ? res.data : res.data?.slots ?? [];
        const normalized = data.map((slot: any) => ({
          ...slot,
          date: (slot.date ?? "").toString().split("T")[0],
          facility_id: slot.facility_id ?? null,
        }));
        setUnavailableSlots(normalized);
      })
      .catch(() => {
        setUnavailableSlots([]);
      });
  }, [dtTempDate, dtTempFacility, hasSlots, requireFacility]);

  const monthStart = startOfMonth(currentDate);
  const monthEnd = endOfMonth(currentDate);
  const startDate = subDays(monthStart, getDay(monthStart));
  const endDate = addDays(monthEnd, 6 - getDay(monthEnd));
  const calendarDays = eachDayOfInterval({ start: startDate, end: endDate });

  const firstOfThisMonth = startOfMonth(startOfToday());
  const canGoPrev = !isBefore(addMonths(currentDate, -1), firstOfThisMonth);

  const handleSimpleChange = useCallback((field: FormField, value: any) => {
    setValues((prev) => {
      if (field.data_type === "checkbox") {
        if (Array.isArray(value)) return { ...prev, [field.field_name]: value };
        if (typeof value === "string" && value !== "") {
          return { ...prev, [field.field_name]: [value] };
        }
        return { ...prev, [field.field_name]: [] };
      }
      return { ...prev, [field.field_name]: value };
    });
  }, []);

  const handleMetaCheckboxToggle = useCallback(
    (field: FormField, option: OptionMeta, checked: boolean) => {
      const normalized = normalizeMetaOption(option);
      const current: MultiMetaSelection =
        (values[field.field_name] as MultiMetaSelection) || [];
      const id = normalized.value;

      let next: MultiMetaSelection;
      if (checked) {
        const exists = current.some((entry) => entry.value === id);
        if (exists) {
          next = current;
        } else {
          next = [
            ...current,
            {
              value: id,
              qty: normalized.requires_qty ? normalized.default_qty ?? 1 : undefined,
              text: normalized.requires_text ? "" : undefined,
            },
          ];
        }
      } else {
        next = current.filter((entry) => entry.value !== id);
      }

      setValues((prev) => ({ ...prev, [field.field_name]: next }));
    },
    [values]
  );

  const handleMetaCheckboxQty = useCallback(
    (field: FormField, option: OptionMeta, rawValue: string) => {
      const normalized = normalizeMetaOption(option);
      const id = normalized.value;
      const current: MultiMetaSelection =
        (values[field.field_name] as MultiMetaSelection) || [];

      let qty: number | undefined;
      if (rawValue === "" || rawValue === null || rawValue === undefined) {
        qty = undefined;
      } else {
        qty = Number(rawValue);
        if (Number.isNaN(qty) || !Number.isFinite(qty)) {
          qty = normalized.default_qty ?? 1;
        } else {
          if (
            normalized.min_qty !== null &&
            normalized.min_qty !== undefined &&
            qty < normalized.min_qty
          ) {
            qty = normalized.min_qty;
          }
          if (
            normalized.max_qty !== null &&
            normalized.max_qty !== undefined &&
            qty > normalized.max_qty
          ) {
            qty = normalized.max_qty;
          }
        }
      }

      setValues((prev) => ({
        ...prev,
        [field.field_name]: current.map((entry) =>
          entry.value === id ? { ...entry, qty } : entry
        ),
      }));
    },
    [values]
  );

  const handleMetaCheckboxText = useCallback(
    (field: FormField, option: OptionMeta, text: string) => {
      const normalized = normalizeMetaOption(option);
      const id = normalized.value;
      const current: MultiMetaSelection =
        (values[field.field_name] as MultiMetaSelection) || [];

      setValues((prev) => ({
        ...prev,
        [field.field_name]: current.map((entry) =>
          entry.value === id ? { ...entry, text } : entry
        ),
      }));
    },
    [values]
  );

  const handleMetaSinglePick = useCallback(
    (field: FormField, value: string) => {
      const meta = (field.options_meta || []).map(normalizeMetaOption);
      const picked = meta.find((option) => option.value === value);
      if (!picked) return;

      const payload: SingleMetaSelection = {
        value,
        qty: picked.requires_qty ? picked.default_qty ?? 1 : undefined,
        text: picked.requires_text ? "" : undefined,
      };

      setValues((prev) => ({ ...prev, [field.field_name]: payload }));
    },
    []
  );

  const handleMetaSingleQty = useCallback(
    (field: FormField, rawValue: string) => {
      const current: SingleMetaSelection | undefined = values[field.field_name];
      if (!current || !current.value) return;

      const meta = (field.options_meta || []).map(normalizeMetaOption);
      const picked = meta.find((option) => option.value === current.value);

      let qty: number | undefined;
      if (rawValue === "" || rawValue === null || rawValue === undefined) {
        qty = undefined;
      } else {
        qty = Number(rawValue);
        if (Number.isNaN(qty) || !Number.isFinite(qty)) {
          qty = picked?.default_qty ?? current.qty ?? 1;
        } else {
          if (picked?.min_qty !== null && picked?.min_qty !== undefined && qty < picked.min_qty) {
            qty = picked.min_qty;
          }
          if (picked?.max_qty !== null && picked?.max_qty !== undefined && qty > picked.max_qty) {
            qty = picked.max_qty;
          }
        }
      }

      setValues((prev) => ({ ...prev, [field.field_name]: { ...current, qty } }));
    },
    [values]
  );

  const handleMetaSingleText = useCallback(
    (field: FormField, text: string) => {
      const current: SingleMetaSelection | undefined = values[field.field_name];
      if (!current || !current.value) return;

      setValues((prev) => ({ ...prev, [field.field_name]: { ...current, text } }));
    },
    [values]
  );

  const addDtSlot = useCallback(() => {
    if (!dtTempDate || !dtTempStart || !dtTempEnd || (requireFacility && !dtTempFacility)) {
      return;
    }

    setDtSlots((prev) => [
      ...prev,
      {
        date: dtTempDate,
        start_time: dtTempStart,
        end_time: dtTempEnd,
        facility_id: requireFacility ? dtTempFacility : undefined,
      },
    ]);

    setDtTempDate(undefined);
    setDtTempStart("");
    setDtTempEnd("");
    setDtTempFacility("");
  }, [dtTempDate, dtTempEnd, dtTempFacility, dtTempStart, requireFacility]);

  const removeDtSlot = useCallback(
    (index: number) => setDtSlots((prev) => prev.filter((_, idx) => idx !== index)),
    []
  );

  const addPlainDate = useCallback(() => {
    if (!plainTempDate) return;
    setPlainDates((prev) => [...prev, plainTempDate]);
    setPlainTempDate(undefined);
  }, [plainTempDate]);

  const removePlainDate = useCallback(
    (index: number) => setPlainDates((prev) => prev.filter((_, idx) => idx !== index)),
    []
  );

  const addDateRange = useCallback(() => {
    if (!rangeStart || !rangeEnd) return;
    if (rangeEnd < rangeStart) return;
    setDateRanges((prev) => [...prev, { from: rangeStart, to: rangeEnd }]);
    setRangeStart(undefined);
    setRangeEnd(undefined);
  }, [rangeEnd, rangeStart]);

  const removeDateRange = useCallback(
    (index: number) => setDateRanges((prev) => prev.filter((_, idx) => idx !== index)),
    []
  );

  const handleFileUpload = useCallback(
    (files: FileList | null) => {
      const picked = Array.from(files || []);
      if (!picked.length) return;

      const accepted: File[] = [];
      const rejected: { file: File; reason: string }[] = [];

      for (const file of picked) {
        const typeOk = file.type
          ? ACCEPT_MIME.has(file.type) || hasAllowedExtension(file.name)
          : hasAllowedExtension(file.name);
        const sizeOk = file.size <= MAX_FILE_BYTES;

        if (!typeOk) {
          rejected.push({ file, reason: "Unsupported type" });
          continue;
        }

        if (!sizeOk) {
          rejected.push({ file, reason: "Too large (>10MB)" });
          continue;
        }

        if (
          attachments.some((existing) => isDuplicateFile(existing, file)) ||
          accepted.some((existing) => isDuplicateFile(existing, file))
        ) {
          rejected.push({ file, reason: "Duplicate" });
          continue;
        }

        accepted.push(file);
      }

      if (rejected.length) {
        console.warn("Some files were skipped:", rejected);
      }

      if (accepted.length) {
        setAttachments((prev) => [...prev, ...accepted]);
      }
    },
    [attachments]
  );

  const removeAttachment = useCallback(
    (index: number) => setAttachments((prev) => prev.filter((_, idx) => idx !== index)),
    []
  );

  const removeExistingAttachment = useCallback(
    (index: number) =>
      setExistingAttachments((prev) => prev.filter((_, idx) => idx !== index)),
    []
  );

  const combinedSlots = useMemo(() => {
    const merged: SelectedSlot[] = [...dtSlots];
    plainDates.forEach((date) => merged.push({ date }));
    return merged;
  }, [dtSlots, plainDates]);

  const isTimeDisabled = useCallback(
    (time: string) =>
      unavailableSlots.some(
        (slot) => slot.start_time && slot.end_time && time >= slot.start_time && time < slot.end_time
      ),
    [unavailableSlots]
  );

  const prepareSubmission = useCallback(() => {
    const encodedValues: Record<string, any> = { ...values };

    submission.form_fields.forEach((field) => {
      if (
        field.options_meta &&
        ["checkbox", "radio", "select"].includes(field.data_type)
      ) {
        encodedValues[field.field_name] = encodeMetaForSubmit(field, values[field.field_name]);
      }
    });

    const formData = buildFormData(encodedValues);

    combinedSlots.forEach((slot, index) => {
      formData.append(`slots[${index}][date]`, format(slot.date, "yyyy-MM-dd"));
      if (slot.start_time) formData.append(`slots[${index}][start_time]`, slot.start_time);
      if (slot.end_time) formData.append(`slots[${index}][end_time]`, slot.end_time);
      if (slot.facility_id) formData.append(`slots[${index}][facility_id]`, slot.facility_id);
    });

    dateRanges.forEach((range, index) => {
      formData.append(`date_ranges[${index}][from]`, format(range.from, "yyyy-MM-dd"));
      formData.append(`date_ranges[${index}][to]`, format(range.to, "yyyy-MM-dd"));
    });

    attachments.forEach((file, index) => {
      formData.append(`attachments[${index}]`, file);
    });

    formData.append(
      "keep_attachments",
      JSON.stringify(existingAttachments.map((attachment) => attachment.id))
    );

    return formData;
  }, [attachments, combinedSlots, dateRanges, existingAttachments, submission.form_fields, values]);

  const resetAfterSubmit = useCallback(() => {
    setSubmitting(false);
    setConfirmOpen(false);
  }, []);

  return {
    state: {
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
    },
    setters: {
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
    },
    derived: {
      hasSlots,
      hasPlainDate,
      hasRange,
      requireFacility,
      dateTimeField,
      plainDateField,
      rangeField,
      canGoPrev,
    },
    actions: {
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
    },
  };
};
