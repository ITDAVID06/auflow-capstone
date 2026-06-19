import React, { useEffect, useState } from "react";
import axios from "axios";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { ReportForm } from "../types";

interface FormPickerProps {
  selectedFormId: number | null;
  onChange: (formId: number | null) => void;
}

export const FormPicker: React.FC<FormPickerProps> = ({ selectedFormId, onChange }) => {
  const [forms, setForms] = useState<ReportForm[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    axios
      .get<ReportForm[]>(route("reports.forms"))
      .then((r) => setForms(r.data))
      .catch(() => setForms([]))
      .finally(() => setLoading(false));
  }, []);

  return (
    <Select
      value={selectedFormId ? String(selectedFormId) : ""}
      onValueChange={(v) => onChange(v ? Number(v) : null)}
      disabled={loading}
    >
      <SelectTrigger className="w-72">
        <SelectValue placeholder={loading ? "Loading forms…" : "Select a form"} />
      </SelectTrigger>
      <SelectContent>
        {forms.map((f) => (
          <SelectItem key={f.id} value={String(f.id)}>
            {f.form_name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
};
