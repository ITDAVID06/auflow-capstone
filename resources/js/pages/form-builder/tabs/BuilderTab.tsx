import React, { useMemo } from "react";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Droppable, Draggable } from "@hello-pangea/dnd";
import { FieldBlock } from "../components/FieldBlock";
import { FileText } from "lucide-react";
import { FormBuilderState, FormField } from "../types/formBuilderTypes";

export function BuilderTab({
  form,
  setForm,
  onBeforeFieldChange,
  onAddFirstField,
}: {
  form: FormBuilderState;
  setForm: React.Dispatch<React.SetStateAction<FormBuilderState>>;
  /** Called before any field mutation so the caller can snapshot undo history. */
  onBeforeFieldChange?: () => void;
  onAddFirstField?: () => void;
}) {
  const locked = Boolean((form as FormBuilderState & { is_locked?: boolean }).is_locked);

  const handleFieldChange = (id: string | number, changes: Partial<FormField>) => {
    if (locked) return;
    onBeforeFieldChange?.();
    setForm((f) => ({
      ...f,
      fields: f.fields.map((field) =>
        String(field.id) === String(id) ? { ...field, ...changes } : field
      ),
    }));
  };

  const handleDeleteField = (id: string | number) => {
    if (locked) return;
    onBeforeFieldChange?.();
    setForm((f) => ({
      ...f,
      fields: f.fields
        .filter((field) => String(field.id) !== String(id))
        .map((field, i) => ({ ...field, field_order: i })),
    }));
  };

  const handleDuplicateField = (id: string | number) => {
    if (locked) return;
    onBeforeFieldChange?.();
    setForm((f) => {
      const fieldToDuplicate = f.fields.find((field) => String(field.id) === String(id));
      if (!fieldToDuplicate) return f;

      const duplicated = {
        ...fieldToDuplicate,
        id: crypto.randomUUID(),
        field_name: `${fieldToDuplicate.field_name}_copy_${Date.now()}`,
        field_order: fieldToDuplicate.field_order + 1,
      };

      const updatedFields = [...f.fields];
      const insertIndex = f.fields.findIndex((field) => String(field.id) === String(id)) + 1;
      updatedFields.splice(insertIndex, 0, duplicated);

      return {
        ...f,
        fields: updatedFields.map((field, i) => ({ ...field, field_order: i })),
      };
    });
  };

  const sorted = useMemo(
    () => [...form.fields].sort((a, b) => a.field_order - b.field_order),
    [form.fields]
  );

  return (
    <div className="flex-1">
      {/* Form Metadata */}
      <div className="mb-6 pb-6 border-b">
        <div className="mb-4">
          <label htmlFor="form-description" className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Form Description</label>
          <Textarea
            id="form-description"
            value={form.description}
            onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
            placeholder="Describe what this form is for..."
            disabled={locked}
            className="resize-none"
            rows={3}
          />
        </div>
      </div>

      {/* Fields Canvas */}
      <div className="min-h-[400px]">
        <Droppable droppableId="fields" direction="vertical" isDropDisabled={locked}>
          {(provided, snapshot) => (
            <div
              ref={provided.innerRef}
              {...provided.droppableProps}
              className={[
                "transition-[background-color,box-shadow] rounded-lg",
                snapshot.isDraggingOver
                  ? "ring-2 ring-primary/30 bg-primary/5"
                  : "",
              ].join(" ")}
            >
              {sorted.length === 0 && (
                <div
                  className="flex flex-col items-center justify-center py-20 text-center"
                  data-tour="builder-field"
                >
                  <div className="w-14 h-14 rounded-2xl bg-muted/50 border border-border/60 flex items-center justify-center mb-4">
                    <FileText size={22} className="text-muted-foreground/50" />
                  </div>
                  <p className="text-sm font-semibold text-foreground/70">Add your first field to start building this form.</p>
                  <p className="text-xs text-muted-foreground mt-1.5 max-w-[220px] leading-relaxed">
                    Drag a component from the left panel, or use the button below.
                  </p>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mt-4"
                    disabled={locked}
                    onClick={() => onAddFirstField?.()}
                  >
                    Add field
                  </Button>
                </div>
              )}

              {sorted.map((field, idx) => (
                <Draggable
                  key={String(field.id)}
                  draggableId={String(field.id)}
                  index={idx}
                  isDragDisabled={locked}
                >
                  {(dragProps) => (
                    <div
                      ref={dragProps.innerRef}
                      {...dragProps.draggableProps}
                      data-tour={idx === 0 ? "builder-field" : undefined}
                      className="mb-3 last:mb-0"
                    >
                      <FieldBlock
                        field={field}
                        onChange={(changes) => handleFieldChange(field.id, changes)}
                        onDelete={() => handleDeleteField(field.id)}
                        onDuplicate={() => handleDuplicateField(field.id)}
                        dragHandleProps={dragProps.dragHandleProps || undefined}
                        locked={locked}
                        allFields={form.fields}
                      />
                    </div>
                  )}
                </Draggable>
              ))}

              {provided.placeholder}
            </div>
          )}
        </Droppable>
      </div>
    </div>
  );
}
