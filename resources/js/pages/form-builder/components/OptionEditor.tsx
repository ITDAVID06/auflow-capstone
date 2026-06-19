import React from "react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import { ChevronDown, Plus, Trash2 } from "lucide-react";
import type { OptionMeta } from "../types/formBuilderTypes";
import { cn } from "@/lib/utils";

/* small helper */
function FieldLabel({
  label,
  help,
  htmlFor,
  className,
}: {
  label: string;
  help?: string;
  htmlFor?: string;
  className?: string;
}) {
  return (
    <div className={cn("flex items-center gap-1", className)}>
      <Label htmlFor={htmlFor} className="text-xs font-medium text-foreground/90">
        {label}
      </Label>
      {help && <span className="text-[10px] text-muted-foreground ml-1">{help}</span>}
    </div>
  );
}

function MetaOptionRow({
  option,
  index,
  onRemove,
  onPatch,
  disabled,
  fieldType,
}: {
  option: OptionMeta;
  index: number;
  onRemove: () => void;
  onPatch: (patch: Partial<OptionMeta>) => void;
  disabled?: boolean;
  fieldType: string; // "checkbox" | "radio" | "select"
}) {
  const [moreOpen, setMoreOpen] = React.useState(false);
  const supportsAdvanced = fieldType === "checkbox" || fieldType === "radio";
  return (
    <div className="rounded-md border border-border/60 p-3 space-y-3 bg-muted/30">
      <div className="flex gap-2">
        <div className="flex-1">
          <FieldLabel label="Choice label" help="What the user sees." />
          <Input
            value={option.label}
            placeholder={`Label ${index + 1}`}
            onChange={(e) => onPatch({ label: e.target.value })}
            disabled={disabled}
          />
        </div>
        <Button variant="ghost" size="icon" onClick={onRemove} disabled={disabled} className="self-end">
          <Trash2 size={16} />
        </Button>
      </div>

      {supportsAdvanced && (
        <>
          <div className="flex items-center gap-3">
            <Switch
              checked={!!option.requires_qty}
              onCheckedChange={(checked) => onPatch({ requires_qty: checked })}
              id={`ask-qty-${index}`}
              disabled={disabled}
            />
            <Label htmlFor={`ask-qty-${index}`} className="text-sm">Ask for quantity?</Label>
            <span className="text-xs text-muted-foreground">Shows a number field next to this choice.</span>
          </div>

          <div className="flex items-center gap-3">
            <Switch
              checked={!!option.requires_text}
              onCheckedChange={(checked) => onPatch({ requires_text: checked })}
              id={`ask-text-${index}`}
              disabled={disabled}
            />
            <Label htmlFor={`ask-text-${index}`} className="text-sm">Ask for text input?</Label>
            <span className="text-xs text-muted-foreground">Shows a small text box next to this choice.</span>
          </div>

          {option.requires_text && (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-2">
              <div className="md:col-span-2">
                <FieldLabel label="Text label" help="Placeholder/label shown before the box" />
                <Input
                  value={option.text_label ?? "Specify"}
                  onChange={(e) => onPatch({ text_label: e.target.value })}
                  placeholder="Specify"
                  disabled={disabled}
                />
              </div>
            </div>
          )}
        </>
      )}

      {supportsAdvanced && option.requires_qty && (
        <div className="space-y-2">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-2">
            <div>
              <FieldLabel label="Label" help='Shown before the number (e.g., "Qty", "Hours")' />
              <Input
                value={option.qty_label ?? "Qty"}
                onChange={(e) => onPatch({ qty_label: e.target.value })}
                placeholder="Qty"
                disabled={disabled}
              />
            </div>
            <div>
              <FieldLabel label="Default" help="Start value" />
              <Input
                type="number"
                value={option.default_qty ?? 1}
                onChange={(e) => onPatch({ default_qty: Number(e.target.value) })}
                placeholder="1"
                disabled={disabled}
                min={0}
              />
            </div>
            <div>
              <FieldLabel label="Min" help="Lowest allowed" />
              <Input
                type="number"
                value={option.min_qty ?? 0}
                onChange={(e) => onPatch({ min_qty: Number(e.target.value) })}
                placeholder="0"
                disabled={disabled}
                min={0}
              />
            </div>
            <div>
              <FieldLabel label="Max" help="Leave blank for no limit" />
              <Input
                type="number"
                value={option.max_qty ?? ""}
                onChange={(e) => onPatch({ max_qty: e.target.value === "" ? null : Number(e.target.value) })}
                placeholder="No limit"
                disabled={disabled}
                min={0}
              />
            </div>
          </div>

          <button
            type="button"
            className="text-xs inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
            onClick={() => setMoreOpen((v) => !v)}
          >
            <ChevronDown className={cn("h-3.5 w-3.5 transition-transform", moreOpen && "rotate-180")} />
            More settings
          </button>

          {moreOpen && (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-2">
              <div>
                <FieldLabel label="Step" help="+/– increment" />
                <Input
                  type="number"
                  value={option.step ?? 1}
                  onChange={(e) => onPatch({ step: Number(e.target.value) })}
                  placeholder="1"
                  disabled={disabled}
                  min={1}
                />
              </div>
              <div>
                <FieldLabel label="Unit" help='Shown after the number (e.g., "pcs", "hrs")' />
                <Input
                  value={option.unit ?? "pcs"}
                  onChange={(e) => onPatch({ unit: e.target.value })}
                  placeholder="pcs"
                  disabled={disabled}
                />
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export function OptionEditor({
  fieldType,
  mode,               // 'simple' | 'meta'
  options = [],
  onChange,
  optionsMeta = [],
  onChangeMeta,
  disabled = false,
}: {
  fieldType: string; // "checkbox" | "radio" | "select" | ...
  mode: "simple" | "meta";
  options?: string[];
  onChange?: (opts: string[]) => void;
  optionsMeta?: OptionMeta[];
  onChangeMeta?: (opts: OptionMeta[]) => void;
  disabled?: boolean;
}) {
  if (mode === "simple") {
    const add = () => onChange?.([...(options || []), ""]);
    const remove = (i: number) => onChange?.(options.filter((_, idx) => idx !== i));
    const patch = (i: number, v: string) =>
      onChange?.(options.map((x, idx) => (idx === i ? v : x)));

    return (
      <div className="space-y-2">
        {options.map((opt, i) => (
          <div key={i} className="flex gap-2 items-center">
            <Input
              value={opt}
              onChange={(e) => patch(i, e.target.value)}
              placeholder={`Option ${i + 1}`}
              disabled={disabled}
            />
            <Button variant="ghost" size="icon" onClick={() => remove(i)} disabled={disabled}>
              <Trash2 size={16} />
            </Button>
          </div>
        ))}
        <Button variant="outline" size="sm" className="mt-1" onClick={add} disabled={disabled}>
          <Plus className="mr-1" size={16} /> Add option
        </Button>
        <p className="text-xs text-muted-foreground">Use this if you don’t need quantities or text per choice.</p>
      </div>
    );
  }

  // meta mode
  const addMeta = () =>
    onChangeMeta?.([
      ...(optionsMeta || []),
      {
        label: "",
        requires_qty: false,
        qty_label: "Qty",
        min_qty: 0,
        max_qty: null,
        step: 1,
        default_qty: 1,
        unit: "pcs",
        requires_text: false,
        text_label: "Specify",
      },
    ]);

  const removeMeta = (i: number) => onChangeMeta?.(optionsMeta.filter((_, idx) => idx !== i));
  const patchMeta = (i: number, patch: Partial<OptionMeta>) =>
    onChangeMeta?.(optionsMeta.map((o, idx) => (idx === i ? { ...o, ...patch } : o)));

  return (
    <div className="space-y-3">
      {optionsMeta.length === 0 && <p className="text-sm text-muted-foreground">No choices yet. Add one below.</p>}
      {optionsMeta.map((opt, i) => (
        <MetaOptionRow
          key={i}
          option={opt}
          index={i}
          onRemove={() => removeMeta(i)}
          onPatch={(patch) => patchMeta(i, patch)}
          disabled={disabled}
          fieldType={fieldType}
        />
      ))}
      <Button variant="outline" size="sm" className="mt-1" onClick={addMeta} disabled={disabled}>
        <Plus className="mr-1" size={16} /> Add choice
      </Button>
      <div className="text-xs text-muted-foreground">
        Checkbox/Radio: you can ask for quantity and/or a small text input per choice. Dropdown does not support these.
      </div>
    </div>
  );
}

export default OptionEditor;
