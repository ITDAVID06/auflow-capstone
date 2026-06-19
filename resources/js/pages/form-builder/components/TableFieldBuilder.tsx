import React, { useState } from "react";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Card } from "@/components/ui/card";
import { Plus, Trash2, GripVertical } from "lucide-react";
import { DragDropContext, Droppable, Draggable, DropResult } from "@hello-pangea/dnd";

export interface TableColumn {
  id: string;
  label: string;
  type: "text" | "number" | "date" | "textarea";
  required: boolean;
}

interface TableFieldOptions {
  table_columns?: TableColumn[];
  min_rows?: number;
  max_rows?: number;
}

interface TableFieldBuilderProps {
  fieldOptions: TableFieldOptions;
  onChange: (options: TableFieldOptions) => void;
  disabled?: boolean;
}

export function TableFieldBuilder({ fieldOptions, onChange, disabled = false }: TableFieldBuilderProps) {
  const columns = fieldOptions.table_columns || [];
  const minRows = fieldOptions.min_rows || 1;
  const maxRows = fieldOptions.max_rows || 10;

  const addColumn = () => {
    const newColumn: TableColumn = {
      id: `col_${Date.now()}`,
      label: `Column ${columns.length + 1}`,
      type: "text",
      required: false,
    };
    onChange({
      ...fieldOptions,
      table_columns: [...columns, newColumn],
    });
  };

  const updateColumn = (id: string, updates: Partial<TableColumn>) => {
    onChange({
      ...fieldOptions,
      table_columns: columns.map((col) => (col.id === id ? { ...col, ...updates } : col)),
    });
  };

  const deleteColumn = (id: string) => {
    onChange({
      ...fieldOptions,
      table_columns: columns.filter((col) => col.id !== id),
    });
  };

  const handleDragEnd = (result: DropResult) => {
    if (!result.destination || disabled) return;

    const items = Array.from(columns);
    const [reordered] = items.splice(result.source.index, 1);
    items.splice(result.destination.index, 0, reordered);

    onChange({
      ...fieldOptions,
      table_columns: items,
    });
  };

  return (
    <div className="space-y-4">
      {/* Row Constraints */}
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-2">
          <Label htmlFor="min-rows" className="text-xs font-semibold">
            Min Rows
          </Label>
          <Input
            id="min-rows"
            type="number"
            min={0}
            max={100}
            value={minRows}
            onChange={(e) => {
              const nextMin = Math.max(0, Math.min(100, parseInt(e.target.value) || 0));
              onChange({
                ...fieldOptions,
                min_rows: nextMin,
                max_rows: Math.max(nextMin, maxRows),
              });
            }}
            disabled={disabled}
            className="h-8 text-sm"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="max-rows" className="text-xs font-semibold">
            Max Rows
          </Label>
          <Input
            id="max-rows"
            type="number"
            min={1}
            max={100}
            value={maxRows}
            onChange={(e) => {
              const nextMax = Math.max(1, Math.min(100, parseInt(e.target.value) || 10));
              onChange({
                ...fieldOptions,
                min_rows: Math.min(minRows, nextMax),
                max_rows: nextMax,
              });
            }}
            disabled={disabled}
            className="h-8 text-sm"
          />
        </div>
      </div>

      {/* Columns Configuration */}
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <Label className="text-xs font-semibold">Table Columns</Label>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={addColumn}
            disabled={disabled || columns.length >= 10}
            className="h-7 text-xs"
          >
            <Plus className="w-3 h-3 mr-1" />
            Add Column
          </Button>
        </div>

        {columns.length === 0 ? (
          <Card className="p-4 text-center">
            <p className="text-sm text-muted-foreground">No columns yet. Click "Add Column" to start.</p>
          </Card>
        ) : (
          <DragDropContext onDragEnd={handleDragEnd}>
            <Droppable droppableId="table-columns">
              {(provided) => (
                <div
                  {...provided.droppableProps}
                  ref={provided.innerRef}
                  className="space-y-2"
                >
                  {columns.map((column, index) => (
                    <Draggable
                      key={column.id}
                      draggableId={column.id}
                      index={index}
                      isDragDisabled={disabled}
                    >
                      {(dragProvided, snapshot) => (
                        <Card
                          ref={dragProvided.innerRef}
                          {...dragProvided.draggableProps}
                          className={[
                            "p-3 space-y-2",
                            snapshot.isDragging ? "shadow-lg border-blue-500" : "",
                          ].join(" ")}
                        >
                          <div className="flex items-center gap-2">
                            <div
                              {...dragProvided.dragHandleProps}
                              className="cursor-grab active:cursor-grabbing"
                            >
                              <GripVertical className="w-4 h-4 text-muted-foreground" />
                            </div>
                            <Input
                              value={column.label}
                              onChange={(e) => updateColumn(column.id, { label: e.target.value })}
                              placeholder="Column Label"
                              disabled={disabled}
                              className="h-7 text-xs flex-1"
                            />
                            <Select
                              value={column.type}
                              onValueChange={(value) =>
                                updateColumn(column.id, { type: value as TableColumn["type"] })
                              }
                              disabled={disabled}
                            >
                              <SelectTrigger className="h-7 text-xs w-28">
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="text">Text</SelectItem>
                                <SelectItem value="number">Number</SelectItem>
                                <SelectItem value="date">Date</SelectItem>
                                <SelectItem value="textarea">Textarea</SelectItem>
                              </SelectContent>
                            </Select>
                            <Button
                              type="button"
                              size="sm"
                              variant="ghost"
                              onClick={() => deleteColumn(column.id)}
                              disabled={disabled}
                              className="h-7 w-7 p-0"
                            >
                              <Trash2 className="w-3.5 h-3.5 text-destructive" />
                            </Button>
                          </div>
                          <div className="flex items-center gap-2 pl-6">
                            <Switch
                              checked={column.required}
                              onCheckedChange={(checked) => updateColumn(column.id, { required: checked })}
                              disabled={disabled}
                              className="scale-75"
                            />
                            <Label className="text-xs text-muted-foreground">Required</Label>
                          </div>
                        </Card>
                      )}
                    </Draggable>
                  ))}
                  {provided.placeholder}
                </div>
              )}
            </Droppable>
          </DragDropContext>
        )}
      </div>
    </div>
  );
}
