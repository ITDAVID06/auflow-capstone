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
import { toast } from "sonner";

import {
  ACCEPT_MIME,
  MAX_FILE_BYTES,
  hasAllowedExtension,
  isDuplicateFile,
} from "@/pages/Forms/utils/fileValidation";
import { getVisibleFields } from "@/pages/Forms/utils/fieldConditions";
import { encodeMetaForSubmit, normalizeMetaOption } from "@/pages/Forms/utils/meta";
import type {
  FormField,
  FormPayload,
  MultiMetaSelection,
  OptionMeta,
  SelectedSlot,
  SingleMetaSelection,
} from "@/types/form";

type AvailabilitySlot = {
  date: string;
  start_time?: string;
  end_time?: string;
  facility_id?: number;
};

type Facility = { id: number; name: string };

type DateRange = { from: Date; to: Date };

type SlotDraft = {
  date?: Date;
  start: string;
  end: string;
  facility: string;
};

type RangeDraft = {
  start?: Date;
  end?: Date;
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

const monthGrid = (currentDate: Date): Date[] => {
  const monthStart = startOfMonth(currentDate);
  const monthEnd = endOfMonth(currentDate);
  const startDate = subDays(monthStart, getDay(monthStart));
  const endDate = addDays(monthEnd, 6 - getDay(monthEnd));

  return eachDayOfInterval({ start: startDate, end: endDate });
};

const slotKey = (slot: SelectedSlot): string => {
  return [
    format(slot.date, "yyyy-MM-dd"),
    slot.start_time ?? "",
    slot.end_time ?? "",
    slot.facility_id ?? "",
  ].join("|");
};

const uniqueBy = <T,>(items: T[], keyFn: (item: T) => string): T[] => {
  const seen = new Set<string>();

  return items.filter((item) => {
    const key = keyFn(item);
    if (seen.has(key)) {
      return false;
    }

    seen.add(key);

    return true;
  });
};

export const useFormSubmissionState = (form: FormPayload, userFullName?: string | null) => {
  const [values, setValues] = useState<Record<string, unknown>>({});
  const [attachments, setAttachments] = useState<File[]>([]);

  const [slotDraftsByField, setSlotDraftsByField] = useState<Record<string, SlotDraft>>({});
  const [dtSlotsByField, setDtSlotsByField] = useState<Record<string, SelectedSlot[]>>({});
  const [plainTempByField, setPlainTempByField] = useState<Record<string, Date | undefined>>({});
  const [plainDatesByField, setPlainDatesByField] = useState<Record<string, Date[]>>({});
  const [rangeDraftsByField, setRangeDraftsByField] = useState<Record<string, RangeDraft>>({});
  const [dateRangesByField, setDateRangesByField] = useState<Record<string, DateRange[]>>({});

  const [facilities, setFacilities] = useState<Facility[]>([]);
  const [unavailableSlotsByField, setUnavailableSlotsByField] = useState<Record<string, AvailabilitySlot[]>>({});
  const [currentDateByField, setCurrentDateByField] = useState<Record<string, Date>>({});

  const [submitting, setSubmitting] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);

  useEffect(() => {
    if (!userFullName) {
      return;
    }

    const initialValues: Record<string, unknown> = {};

    form.fields.forEach((field) => {
      if (field.data_type === "text" && field.field_options?.auto_fill_name) {
        initialValues[field.field_name] = userFullName;
      }
    });

    if (Object.keys(initialValues).length > 0) {
      setValues((prev) => ({ ...prev, ...initialValues }));
    }
  }, [form.fields, userFullName]);

  const visibleFields = useMemo(() => getVisibleFields(form.fields, values), [form.fields, values]);

  useEffect(() => {
    const visibleFieldNames = new Set(visibleFields.map((field) => field.field_name));

    setValues((previous) => {
      const next = Object.fromEntries(
        Object.entries(previous).filter(([fieldName]) => visibleFieldNames.has(fieldName))
      );

      return Object.keys(next).length === Object.keys(previous).length ? previous : next;
    });
  }, [visibleFields]);

  const plainDateFields = useMemo(
    () =>
      visibleFields.filter(
        (field) =>
          field.data_type === "date" &&
          !field.use_slots &&
          !field.require_facility &&
          (field.date_mode ?? "single") === "single"
      ),
    [visibleFields]
  );

  const dateTimeFields = useMemo(
    () => visibleFields.filter((field) => field.data_type === "date" && Boolean(field.use_slots)),
    [visibleFields]
  );

  const rangeFields = useMemo(
    () =>
      visibleFields.filter(
        (field) =>
          field.data_type === "date" &&
          !field.use_slots &&
          !field.require_facility &&
          (field.date_mode ?? "single") === "range"
      ),
    [visibleFields]
  );

  useEffect(() => {
    const activeFieldNames = new Set(
      [...plainDateFields, ...dateTimeFields, ...rangeFields].map((field) => field.field_name)
    );

    const retainByActiveFields = <T,>(prev: Record<string, T>, emptyValue: T): Record<string, T> => {
      const next: Record<string, T> = {};
      activeFieldNames.forEach((fieldName) => {
        next[fieldName] = prev[fieldName] ?? emptyValue;
      });

      return next;
    };

    setSlotDraftsByField((prev) => retainByActiveFields(prev, { start: "", end: "", facility: "" }));
    setDtSlotsByField((prev) => retainByActiveFields(prev, []));
    setPlainTempByField((prev) => retainByActiveFields(prev, undefined));
    setPlainDatesByField((prev) => retainByActiveFields(prev, []));
    setRangeDraftsByField((prev) => retainByActiveFields(prev, {}));
    setDateRangesByField((prev) => retainByActiveFields(prev, []));
    setUnavailableSlotsByField((prev) => retainByActiveFields(prev, []));
    setCurrentDateByField((prev) => retainByActiveFields(prev, new Date()));
  }, [plainDateFields, dateTimeFields, rangeFields]);

  const timeSlots = useMemo(() => generateTimeSlots(), []);

  useEffect(() => {
    axios.get("/admin/facilities/active").then((res) => {
      setFacilities(Array.isArray(res.data) ? res.data : []);
    });
  }, []);

  useEffect(() => {
    let alive = true;

    const run = async () => {
      await Promise.all(
        dateTimeFields.map(async (field) => {
          const fieldName = field.field_name;
          const draft = slotDraftsByField[fieldName];
          const selectedDate = draft?.date;

          if (!selectedDate || isBefore(selectedDate, startOfToday())) {
            if (alive) {
              setUnavailableSlotsByField((prev) => ({ ...prev, [fieldName]: [] }));
            }

            return;
          }

          const params = new URLSearchParams({ date: format(selectedDate, "yyyy-MM-dd") });
          if (field.require_facility) {
            params.append("mode", "datetime_facility");
            if (draft?.facility) {
              params.append("facility_id", draft.facility);
            }
          } else {
            params.append("mode", "datetime");
          }

          try {
            const res = await axios.get(`/admin/facilities/slots/availability?${params.toString()}`);
            const data = Array.isArray(res.data) ? res.data : res.data?.slots ?? [];
            const normalized = data.map((slot: any) => ({
              ...slot,
              date: (slot.date ?? "").toString().split("T")[0],
            }));

            if (alive) {
              setUnavailableSlotsByField((prev) => ({ ...prev, [fieldName]: normalized }));
            }
          } catch (error: any) {
            console.error("Availability fetch failed:", error.response?.data || error.message);
            if (alive) {
              setUnavailableSlotsByField((prev) => ({ ...prev, [fieldName]: [] }));
            }
          }
        })
      );
    };

    void run();

    return () => {
      alive = false;
    };
  }, [dateTimeFields, slotDraftsByField]);

  const handleSimpleChange = useCallback((field: FormField, value: unknown) => {
    setValues((prev) => {
      if (field.data_type === "file") {
        return { ...prev, [field.field_name]: value instanceof File ? value : null };
      }

      if (field.data_type === "checkbox") {
        if (Array.isArray(value)) {
          return { ...prev, [field.field_name]: value };
        }
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
      const meta = (field.options_meta || []).map(normalizeMetaOption);
      const current: MultiMetaSelection = (values[field.field_name] as MultiMetaSelection) || [];
      const optionId = (option.value ?? option.label ?? "").toString();

      let next: MultiMetaSelection;
      if (checked) {
        const exists = current.find((entry) => entry.value === optionId);
        if (exists) {
          next = current;
        } else {
          const normalized = meta.find((m) => m.value === optionId);
          next = [
            ...current,
            {
              value: optionId,
              qty: normalized?.requires_qty ? normalized.default_qty ?? 1 : undefined,
              text: normalized?.requires_text ? "" : undefined,
            },
          ];
        }
      } else {
        next = current.filter((entry) => entry.value !== optionId);
      }

      setValues((prev) => ({ ...prev, [field.field_name]: next }));
    },
    [values]
  );

  const handleMetaCheckboxQty = useCallback(
    (field: FormField, option: OptionMeta, rawValue: string) => {
      const optionId = (option.value ?? option.label ?? "").toString();
      const current: MultiMetaSelection = (values[field.field_name] as MultiMetaSelection) || [];
      const meta = (field.options_meta || []).map(normalizeMetaOption);
      const definition = meta.find((entry) => entry.value === optionId);

      let qty: number | undefined;
      if (rawValue === "" || rawValue === null || rawValue === undefined) {
        qty = undefined;
      } else {
        qty = Number(rawValue);
        if (isNaN(qty) || !isFinite(qty)) {
          qty = definition?.default_qty ?? 1;
        } else {
          if (definition?.min_qty !== null && definition?.min_qty !== undefined && qty < definition.min_qty) {
            qty = definition.min_qty;
          }
          if (definition?.max_qty !== null && definition?.max_qty !== undefined && qty > definition.max_qty) {
            qty = definition.max_qty;
          }
        }
      }

      setValues((prev) => ({
        ...prev,
        [field.field_name]: current.map((entry) => (entry.value === optionId ? { ...entry, qty } : entry)),
      }));
    },
    [values]
  );

  const handleMetaCheckboxText = useCallback(
    (field: FormField, option: OptionMeta, text: string) => {
      const optionId = (option.value ?? option.label ?? "").toString();
      const current: MultiMetaSelection = (values[field.field_name] as MultiMetaSelection) || [];

      setValues((prev) => ({
        ...prev,
        [field.field_name]: current.map((entry) => (entry.value === optionId ? { ...entry, text } : entry)),
      }));
    },
    [values]
  );

  const handleMetaSinglePick = useCallback((field: FormField, value: string) => {
    const meta = (field.options_meta || []).map(normalizeMetaOption);
    const picked = meta.find((entry) => entry.value === value);
    if (!picked) {
      return;
    }

    const payload: SingleMetaSelection = {
      value,
      qty: picked.requires_qty ? picked.default_qty ?? 1 : undefined,
      text: picked.requires_text ? "" : undefined,
    };

    setValues((prev) => ({ ...prev, [field.field_name]: payload }));
  }, []);

  const handleMetaSingleQty = useCallback(
    (field: FormField, rawValue: string) => {
      const current: SingleMetaSelection | undefined = values[field.field_name] as SingleMetaSelection | undefined;
      if (!current || !current.value) {
        return;
      }

      const meta = (field.options_meta || []).map(normalizeMetaOption);
      const picked = meta.find((entry) => entry.value === current.value);

      let qty: number | undefined;
      if (rawValue === "" || rawValue === null || rawValue === undefined) {
        qty = undefined;
      } else {
        qty = Number(rawValue);
        if (isNaN(qty) || !isFinite(qty)) {
          qty = picked?.default_qty ?? 1;
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
      const current: SingleMetaSelection | undefined = values[field.field_name] as SingleMetaSelection | undefined;
      if (!current || !current.value) {
        return;
      }

      setValues((prev) => ({ ...prev, [field.field_name]: { ...current, text } }));
    },
    [values]
  );

  const setSlotDraftDate = useCallback((fieldName: string, date: Date | undefined) => {
    setSlotDraftsByField((prev) => ({ ...prev, [fieldName]: { ...(prev[fieldName] ?? { start: "", end: "", facility: "" }), date } }));
  }, []);

  const setSlotDraftStart = useCallback((fieldName: string, value: string) => {
    setSlotDraftsByField((prev) => ({ ...prev, [fieldName]: { ...(prev[fieldName] ?? { start: "", end: "", facility: "" }), start: value } }));
  }, []);

  const setSlotDraftEnd = useCallback((fieldName: string, value: string) => {
    setSlotDraftsByField((prev) => ({ ...prev, [fieldName]: { ...(prev[fieldName] ?? { start: "", end: "", facility: "" }), end: value } }));
  }, []);

  const setSlotDraftFacility = useCallback((fieldName: string, value: string) => {
    setSlotDraftsByField((prev) => ({ ...prev, [fieldName]: { ...(prev[fieldName] ?? { start: "", end: "", facility: "" }), facility: value } }));
  }, []);

  const addDtSlot = useCallback((fieldName: string, requireFacility: boolean) => {
    const draft = slotDraftsByField[fieldName];

    if (!draft?.date || isBefore(draft.date, startOfToday())) {
      return;
    }

    if (!draft.start || !draft.end || (requireFacility && !draft.facility)) {
      return;
    }

    const nextSlot: SelectedSlot = {
      date: draft.date,
      start_time: draft.start,
      end_time: draft.end,
      facility_id: draft.facility,
    };

    setDtSlotsByField((prev) => {
      const current = prev[fieldName] ?? [];
      const merged = uniqueBy([...current, nextSlot], slotKey);

      return {
        ...prev,
        [fieldName]: merged,
      };
    });

    setSlotDraftsByField((prev) => ({ ...prev, [fieldName]: { start: "", end: "", facility: "" } }));
  }, [slotDraftsByField]);

  const removeDtSlot = useCallback((fieldName: string, index: number) => {
    setDtSlotsByField((prev) => ({
      ...prev,
      [fieldName]: (prev[fieldName] ?? []).filter((_, idx) => idx !== index),
    }));
  }, []);

  const setPlainTempDate = useCallback((fieldName: string, date: Date | undefined) => {
    setPlainTempByField((prev) => ({ ...prev, [fieldName]: date }));
  }, []);

  const addPlainDate = useCallback((fieldName: string) => {
    const date = plainTempByField[fieldName];
    if (!date) {
      return;
    }

    setPlainDatesByField((prev) => ({
      ...prev,
      [fieldName]: uniqueBy([...(prev[fieldName] ?? []), date], (item) => format(item, "yyyy-MM-dd")),
    }));

    setPlainTempByField((prev) => ({ ...prev, [fieldName]: undefined }));
  }, [plainTempByField]);

  const removePlainDate = useCallback((fieldName: string, index: number) => {
    setPlainDatesByField((prev) => ({
      ...prev,
      [fieldName]: (prev[fieldName] ?? []).filter((_, idx) => idx !== index),
    }));
  }, []);

  const setRangeStart = useCallback((fieldName: string, date: Date | undefined) => {
    setRangeDraftsByField((prev) => ({ ...prev, [fieldName]: { ...(prev[fieldName] ?? {}), start: date } }));
  }, []);

  const setRangeEnd = useCallback((fieldName: string, date: Date | undefined) => {
    setRangeDraftsByField((prev) => ({ ...prev, [fieldName]: { ...(prev[fieldName] ?? {}), end: date } }));
  }, []);

  const addDateRange = useCallback((fieldName: string) => {
    const draft = rangeDraftsByField[fieldName] ?? {};
    if (!draft.start || !draft.end || isBefore(draft.end, draft.start)) {
      return;
    }

    setDateRangesByField((prev) => ({
      ...prev,
      [fieldName]: [...(prev[fieldName] ?? []), { from: draft.start!, to: draft.end! }],
    }));

    setRangeDraftsByField((prev) => ({ ...prev, [fieldName]: {} }));
  }, [rangeDraftsByField]);

  const removeDateRange = useCallback((fieldName: string, index: number) => {
    setDateRangesByField((prev) => ({
      ...prev,
      [fieldName]: (prev[fieldName] ?? []).filter((_, idx) => idx !== index),
    }));
  }, []);

  const handleFileUpload = useCallback(
    (files: FileList | null) => {
      const picked = Array.from(files || []);
      if (!picked.length) {
        return;
      }

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
          attachments.some((attached) => isDuplicateFile(attached, file)) ||
          accepted.some((attached) => isDuplicateFile(attached, file))
        ) {
          rejected.push({ file, reason: "Duplicate" });
          continue;
        }

        accepted.push(file);
      }

      if (rejected.length) {
        const message = rejected
          .slice(0, 5)
          .map((entry) => `${entry.file.name}: ${entry.reason}`)
          .join("\n");
        toast.warning("Some files were skipped", { description: message });
      }

      if (accepted.length) {
        setAttachments((prev) => [...prev, ...accepted]);
      }
    },
    [attachments]
  );

  const removeAttachment = useCallback((index: number) => {
    setAttachments((prev) => prev.filter((_, idx) => idx !== index));
  }, []);

  const allPlainDates = useMemo(
    () =>
      Object.entries(plainDatesByField).flatMap(([fieldName, dates]) => {
        if (dates.length > 0) {
          return dates;
        }

        const tempDate = plainTempByField[fieldName];

        return tempDate ? [tempDate] : [];
      }),
    [plainDatesByField, plainTempByField]
  );
  const allDateRanges = useMemo(() => Object.values(dateRangesByField).flat(), [dateRangesByField]);
  const allSlots = useMemo(() => Object.values(dtSlotsByField).flat(), [dtSlotsByField]);

  const unavailableDates = useMemo(() => {
    const dates = Object.values(unavailableSlotsByField)
      .flat()
      .map((slot) => slot.date)
      .filter(Boolean);

    return Array.from(new Set(dates));
  }, [unavailableSlotsByField]);

  const prepareSubmission = useCallback(() => {
    const formData = new FormData();

    const plainDateByField = plainDateFields.reduce<Record<string, Date | null>>((carry, field) => {
      const fieldName = field.field_name;
      const selectedDate = plainDatesByField[fieldName]?.[0] ?? plainTempByField[fieldName] ?? null;

      carry[fieldName] = selectedDate;
      return carry;
    }, {});

    visibleFields.forEach((field) => {
      const key = field.field_name;

      if (field.data_type === "date") {
        const isRangeMode = (field.date_mode ?? "single") === "range";
        const isSlotBasedDate = Boolean(field.use_slots || field.require_facility || isRangeMode);

        if (isSlotBasedDate) {
          return;
        }

        const dateValue = plainDateByField[key];
        if (dateValue) {
          formData.append(key, format(dateValue, "yyyy-MM-dd"));
        }

        return;
      }

      if (field.data_type === "file") {
        const fileValue = values[key];
        if (fileValue instanceof File) {
          formData.append(key, fileValue);
        }

        return;
      }

      let fieldValue = values[key];
      const hasMetaOptions = (field.options_meta || []).length > 0;
      if (hasMetaOptions && ["checkbox", "radio", "select"].includes(field.data_type)) {
        fieldValue = encodeMetaForSubmit(field, fieldValue);
      }

      if (typeof fieldValue === "undefined") {
        return;
      }

      if (Array.isArray(fieldValue)) {
        if (field.data_type === "table") {
          formData.append(key, JSON.stringify(fieldValue));
        } else {
          fieldValue.forEach((entry) => {
            formData.append(`${key}[]`, entry == null ? "" : String(entry));
          });
        }

        return;
      }

      if (typeof fieldValue === "boolean") {
        formData.append(key, fieldValue ? "1" : "0");

        return;
      }

      if (fieldValue === null) {
        formData.append(key, "");

        return;
      }

      formData.append(key, String(fieldValue));
    });

    // Slots: iterate field-keyed so each entry carries its field_name,
    // preserving which slot belongs to which date field when a form has
    // multiple slot-based date fields.
    let slotIndex = 0;
    Object.entries(dtSlotsByField).forEach(([fieldName, slots]) => {
      slots.forEach((slot) => {
        formData.append(`slots[${slotIndex}][field_name]`, fieldName);
        formData.append(`slots[${slotIndex}][date]`, format(slot.date, "yyyy-MM-dd"));
        if (slot.start_time) {
          formData.append(`slots[${slotIndex}][start_time]`, slot.start_time);
        }
        if (slot.end_time) {
          formData.append(`slots[${slotIndex}][end_time]`, slot.end_time);
        }
        if (slot.facility_id) {
          formData.append(`slots[${slotIndex}][facility_id]`, slot.facility_id);
        }
        slotIndex++;
      });
    });

    // Date ranges: iterate field-keyed for the same reason.
    let rangeIndex = 0;
    Object.entries(dateRangesByField).forEach(([fieldName, ranges]) => {
      ranges.forEach((range) => {
        formData.append(`date_ranges[${rangeIndex}][field_name]`, fieldName);
        formData.append(`date_ranges[${rangeIndex}][from]`, format(range.from, "yyyy-MM-dd"));
        formData.append(`date_ranges[${rangeIndex}][to]`, format(range.to, "yyyy-MM-dd"));
        rangeIndex++;
      });
    });

    attachments.forEach((file, index) => {
      formData.append(`attachments[${index}]`, file);
    });

    return formData;
  }, [
    attachments,
    dateRangesByField,
    dtSlotsByField,
    plainDateFields,
    plainDatesByField,
    plainTempByField,
    values,
    visibleFields,
  ]);

  const resetAfterSubmit = useCallback(() => {
    setSubmitting(false);
    setConfirmOpen(false);
  }, []);

  const firstOfThisMonth = useMemo(() => startOfMonth(startOfToday()), []);

  const setCurrentDate = useCallback((fieldName: string, date: Date) => {
    setCurrentDateByField((prev) => ({ ...prev, [fieldName]: date }));
  }, []);

  const getCalendarDays = useCallback(
    (fieldName: string) => {
      const date = currentDateByField[fieldName] ?? new Date();
      return monthGrid(date);
    },
    [currentDateByField]
  );

  const canGoPrev = useCallback(
    (fieldName: string) => {
      const date = currentDateByField[fieldName] ?? new Date();
      return !isBefore(addMonths(date, -1), firstOfThisMonth);
    },
    [currentDateByField, firstOfThisMonth]
  );

  const isTimeDisabled = useCallback(
    (fieldName: string, time: string) =>
      (unavailableSlotsByField[fieldName] ?? []).some(
        (slot) => slot.start_time && slot.end_time && time >= slot.start_time && time < slot.end_time
      ),
    [unavailableSlotsByField]
  );

  return {
    state: {
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
    },
    setters: {
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
    },
    derived: {
      visibleFields,
      plainDateFields,
      dateTimeFields,
      rangeFields,
      timeSlots,
      getCalendarDays,
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
      prepareSubmission,
      resetAfterSubmit,
      isTimeDisabled,
    },
  };
};
