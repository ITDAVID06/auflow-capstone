import React from "react";
import { Droppable, Draggable } from "@hello-pangea/dnd";
import {
  Type, Mail, Phone, Calendar, FileText,
  CheckSquare, Dot, ChevronDown, Upload, Hash, Table,
  Minus, Heading, Image
} from "lucide-react";
import { FIELD_TYPE_DEFINITIONS } from "../config/fieldTypeRegistry";

const iconsByType = {
  text: Type,
  email: Mail,
  phone: Phone,
  date: Calendar,
  textarea: FileText,
  checkbox: CheckSquare,
  radio: Dot,
  select: ChevronDown,
  file: Upload,
  number: Hash,
  table: Table,
  section: Minus,
  heading: Heading,
  image: Image,
} as const;

export function FieldPalette({
  onAddField,
  disabled = false,
}: {
  onAddField: (type: string, extras?: Record<string, any>) => void;
  disabled?: boolean;
}) {
  return (
    <div className="h-full">
      <div className="mb-3 flex items-center justify-between px-0.5">
        <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
          Components
        </h3>
        <span className="text-xs text-muted-foreground/60 tabular-nums">
          {FIELD_TYPE_DEFINITIONS.length}
        </span>
      </div>
      <Droppable droppableId="palette" isDropDisabled>
        {(dropProvided) => (
          <div
            ref={dropProvided.innerRef}
            {...dropProvided.droppableProps}
            className="flex flex-col gap-1"
          >
            {FIELD_TYPE_DEFINITIONS.map((ft, idx) => {
              const Icon = iconsByType[ft.type];
              const draggableId = `palette-${ft.type}`;
              const extras =
                ft.type === "date"
                  ? { use_slots: false, require_facility: false, date_mode: "single" as const }
                  : {};

              return (
                <Draggable
                  key={draggableId}
                  draggableId={draggableId}
                  index={idx}
                  isDragDisabled={disabled}
                >
                  {(dragProvided, snapshot) => (
                    <div
                      ref={dragProvided.innerRef}
                      {...dragProvided.draggableProps}
                      {...dragProvided.dragHandleProps}
                      role="button"
                      tabIndex={disabled ? -1 : 0}
                      aria-label={ft.hint || `Add ${ft.label} field`}
                      onKeyDown={(e) => {
                        if (!disabled && (e.key === "Enter" || e.key === " ")) {
                          e.preventDefault();
                          onAddField(ft.type, extras);
                        }
                      }}
                      className={[
                        "group flex items-center gap-2.5 rounded-md border px-2.5 py-2 transition-[border-color,background-color,transform,box-shadow]",
                        "border-border/60 bg-card text-foreground",
                        disabled ? "opacity-50 pointer-events-none" : "cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50",
                        snapshot.isDragging
                          ? "border-border/80 bg-muted/60 scale-[1.01]"
                          : "hover:border-border hover:bg-muted/50",
                      ].join(" ")}
                      onClick={() => !disabled && onAddField(ft.type, extras)}
                    >
                      <div className={[
                        "w-6 h-6 rounded flex items-center justify-center flex-shrink-0 transition-[border-color,background-color]",
                        snapshot.isDragging
                          ? "bg-muted text-foreground/70"
                          : "bg-muted/60 text-muted-foreground group-hover:bg-muted group-hover:text-foreground",
                      ].join(" ")}>
                        <Icon size={13} aria-hidden="true" />
                      </div>
                      <span className="text-xs font-medium leading-none truncate">
                        {ft.label}
                      </span>
                    </div>
                  )}
                </Draggable>
              );
            })}
            {dropProvided.placeholder}
          </div>
        )}
      </Droppable>
    </div>
  );
}
