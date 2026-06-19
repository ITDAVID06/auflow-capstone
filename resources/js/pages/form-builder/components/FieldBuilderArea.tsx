import React, { useMemo } from "react";
import { Droppable, Draggable } from "@hello-pangea/dnd";
import { FormField } from "../types/formBuilderTypes";
import { FieldBlock } from "./FieldBlock";
import { FileText } from "lucide-react";

interface Props {
  fields: FormField[];
  onFieldChange: (id: string | number, changes: Partial<FormField>) => void;
  onFieldDelete: (id: string | number) => void;
}

export function FieldBuilderArea({
  fields,
  onFieldChange,
  onFieldDelete,
}: Props) {
  const sortedFields = useMemo(
    () => [...fields].sort((a, b) => a.field_order - b.field_order),
    [fields],
  );

  return (
    <div className="border border-dashed border-gray-300 rounded-lg py-8 px-1 bg-white">
      <div className="w-full max-w-2xl mx-auto">
        <Droppable droppableId="fields">
          {(provided) => (
            <div ref={provided.innerRef} {...provided.droppableProps}>
              {fields.length === 0 && (
                <div className="flex flex-col items-center text-muted-foreground">
                  <FileText size={32} className="mb-2" />
                  <span>No fields added</span>
                </div>
              )}
              {sortedFields
                .map((field, idx) => (
                  <Draggable key={String(field.id)} draggableId={String(field.id)} index={idx}>
                    {(dragProps) => (
                      <div
                        ref={dragProps.innerRef}
                        {...dragProps.draggableProps}
                        className="mb-4 last:mb-0"
                      >
                        <FieldBlock
                          field={field}
                          onChange={(changes) => onFieldChange(field.id, changes)}
                          onDelete={() => onFieldDelete(field.id)}
                          dragHandleProps={dragProps.dragHandleProps || undefined}
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
