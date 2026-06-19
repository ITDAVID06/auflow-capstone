import React, { useState, useEffect, useCallback } from "react";
import AppLayout from "@/layouts/app-layout";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { usePage, router } from "@inertiajs/react";
import { toast } from "sonner";
import { Sparkles } from "lucide-react";

import { FormBuilderState, FormField } from "./types/formBuilderTypes";
import { useFormBuilderActions } from "./hooks/useFormBuilderActions";
import { useAutoSave } from "./hooks/useAutoSave";
import { useFormHistory } from "./hooks/useFormHistory";
import { BuilderTab } from "./tabs/BuilderTab";
import { FieldPalette } from "./components/FieldPalette";
import { SettingsPanel } from "./tabs/SettingsPanel";
import { FormBuilderHeader } from "./components/FormBuilderHeader";
import CreateWorkflowPromptModal from "./components/CreateWorkflowPromptModal";
import { PreviewModal } from "./components/PreviewModal";
import { getFieldTypeLabel, FIELD_DEFAULT_OPTIONS } from "./config/fieldTypeRegistry";

import { DragDropContext, DropResult } from "@hello-pangea/dnd";

const defaultForm: FormBuilderState = {
  form_name: "",
  description: "",
  version: 1,
  status: "Inactive",
  fields: [],
  email_notifications: false,
  submission_limit: "",
  permissions: [],
};

interface FormBuilderPageProps {
  form?: Partial<FormBuilderState> | null;
  flash?: {
    success?: string;
    error?: string;
  };
}

function FormBuilderPage() {
  const { form: formFromBackend, flash } = usePage<FormBuilderPageProps>().props;

  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [form, setForm] = useState<FormBuilderState>(
    formFromBackend ? { ...defaultForm, ...formFromBackend } : defaultForm
  );
  const [isSaving, setIsSaving] = useState(false);
  const [localSuccess, setLocalSuccess] = useState<string | null>(null);
  const [showPreview, setShowPreview] = useState(false);

  const { save, error } = useFormBuilderActions(form, setForm, setIsSaving);
  const locked = Boolean((form as FormBuilderState & { is_locked?: boolean }).is_locked);

  // Auto-save draft (only fires for persisted forms)
  const { status: autoSaveStatus, lastSaved } = useAutoSave(form);

  // Undo / Redo
  const setFormFields = useCallback(
    (fields: FormField[]) =>
      setForm((f) => ({ ...f, fields: fields.map((fld, i) => ({ ...fld, field_order: i })) })),
    []
  );
  const { pushState, undo, redo, canUndo, canRedo, clearHistory } = useFormHistory(form.fields, setFormFields);

  useEffect(() => {
    if (formFromBackend) setForm({ ...defaultForm, ...formFromBackend });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [formFromBackend?.id]);

  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success);
      setLocalSuccess(flash.success);
      const t = setTimeout(() => setLocalSuccess(null), 5000);
      return () => clearTimeout(t);
    }
    if (flash?.error) toast.error(flash.error);
  }, [flash?.success, flash?.error]);

  // Unsaved changes guard — warn before browser/tab close
  useEffect(() => {
    if (locked) return;
    const handler = (e: BeforeUnloadEvent) => {
      if (canUndo) {
        e.preventDefault();
      }
    };
    window.addEventListener("beforeunload", handler);
    return () => window.removeEventListener("beforeunload", handler);
  }, [locked, canUndo]);

  const createFieldFromType = (type: string, extras: Partial<FormField> = {}): FormField => {
    return {
      id: crypto.randomUUID(),
      form_id: form.id ?? 0,
      field_name: `field_${type}_${Date.now()}`,
      label: getFieldTypeLabel(type),
      data_type: type,
      is_required: false,
      is_publicly_verifiable: true,
      is_sensitive: false,
      options: ["select", "radio", "checkbox"].includes(type) ? ["Option 1", "Option 2"] : [],
      field_order: form.fields.length,
      placeholder: "",
      field_options: FIELD_DEFAULT_OPTIONS[type as keyof typeof FIELD_DEFAULT_OPTIONS] ?? null,
      ...extras,
    };
  };

  const handleAddField = (type: string, extras: Partial<FormField> = {}) => {
    if (locked) return;
    pushState(form.fields);
    const newField = createFieldFromType(type, extras);
    setForm((f) => ({
      ...f,
      fields: [...f.fields, newField].map((fld, i) => ({ ...fld, field_order: i })),
    }));
  };

  const handleSave = async () => {
    if (locked) return;
    const savedFormId = await save();
    if (savedFormId !== null) clearHistory();
    if (!formFromBackend?.id && savedFormId) {
      setForm((f) => ({ ...f, id: savedFormId }));
      setShowConfirmModal(true);
    }
  };

  const handleDragEnd = (result: DropResult) => {
    if (!result.destination || locked) return;

    const { source, destination, draggableId } = result;

    if (
      source.droppableId === destination.droppableId &&
      source.index === destination.index
    ) {
      return;
    }

    if (source.droppableId === "fields" && destination.droppableId === "fields") {
      pushState(form.fields);
      const items = Array.from(form.fields);
      const [removed] = items.splice(source.index, 1);
      items.splice(destination.index, 0, removed);
      setForm((f) => ({
        ...f,
        fields: items.map((fld, i) => ({ ...fld, field_order: i })),
      }));
      return;
    }

    if (source.droppableId === "palette" && destination.droppableId === "fields") {
      pushState(form.fields);
      const type = draggableId.replace(/^palette-/, "");
      const extras = type === "date" ? { use_slots: false, require_facility: false, date_mode: "single" as const } : {};
      const newField = createFieldFromType(type, extras);

      const items = Array.from(form.fields);
      items.splice(destination.index, 0, newField);
      setForm((f) => ({
        ...f,
        fields: items.map((fld, i) => ({ ...fld, field_order: i })),
      }));
      return;
    }
  };

  return (
    <AppLayout>
      {/* Sticky Header */}
      <FormBuilderHeader
        form={form}
        setForm={setForm}
        isSaving={isSaving}
        onSave={handleSave}
        onPreview={() => setShowPreview(true)}
        locked={locked}
        canUndo={canUndo}
        canRedo={canRedo}
        onUndo={undo}
        onRedo={redo}
        autoSaveStatus={autoSaveStatus}
        lastSaved={lastSaved}
      />

      {/* Main Content Area */}
      <div className="min-h-screen bg-muted/20">
        <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
          <DragDropContext onDragEnd={handleDragEnd}>
            <div className="grid grid-cols-1 items-start gap-5 xl:grid-cols-[240px_1fr_280px]">
              {/* Left: Component Library */}
              <div className="order-2 xl:order-1" data-tour="builder-palette">
                <div className="xl:sticky xl:top-20">
                  <div className="rounded-lg border border-border/70 bg-card p-4">
                    <FieldPalette onAddField={handleAddField} disabled={locked} />
                  </div>
                </div>
              </div>

              {/* Middle: Canvas (White Paper Sheet) */}
              <div className="order-1 xl:order-2" data-tour="builder-canvas">
                {/* Alerts */}
                {localSuccess && (
                  <Alert className="mb-4 border-emerald-500/20 bg-emerald-500/10">
                    <Sparkles className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    <AlertDescription className="text-emerald-900 dark:text-emerald-200">
                      {localSuccess}
                    </AlertDescription>
                  </Alert>
                )}
                {error && (
                  <Alert variant="destructive" className="mb-4">
                    <AlertDescription>{error}</AlertDescription>
                  </Alert>
                )}

                {/* White Paper-Like Canvas */}
                <div className="rounded-lg border border-border/70 bg-card p-6 min-h-[600px]">
                  <BuilderTab
                    form={form}
                    setForm={setForm}
                    onBeforeFieldChange={() => pushState(form.fields)}
                    onAddFirstField={() => handleAddField("text")}
                  />
                </div>
              </div>

              {/* Right: Global Settings Panel */}
              <div className="order-3 xl:order-3" data-tour="builder-settings">
                <div className="xl:sticky xl:top-20">
                  <div className="rounded-lg border border-border/70 bg-card p-4">
                    <SettingsPanel
                      form={form}
                      setForm={setForm}
                      disabled={locked}
                    />
                  </div>
                </div>
              </div>
            </div>
          </DragDropContext>

          {/* Modals */}
          {showPreview && <PreviewModal form={form} onClose={() => setShowPreview(false)} />}

          <CreateWorkflowPromptModal
            open={showConfirmModal}
            onClose={() => {
              setShowConfirmModal(false);
              router.visit(route("admin.forms.index"));
            }}
            onConfirm={(formId) => router.visit(route("admin.workflows.create") + `?form_id=${formId}`)}
            formId={form.id ?? 0}
          />
        </div>
      </div>
    </AppLayout>
  );
}

export default FormBuilderPage;
