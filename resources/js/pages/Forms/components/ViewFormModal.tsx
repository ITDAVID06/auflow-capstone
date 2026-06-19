import React from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { FormRenderer } from "@/components/forms/FormRenderer";
import type { FormField } from "@/types/form";
import {
  FileText,
  Lock,
  Eye,
  Asterisk,
  MinusCircle,
} from "lucide-react";

interface ViewFormModalProps {
  form: {
    id: number;
    form_name: string;
    form_code: string;
    description: string | null;
    version: number | string;
    status: string;
    category_name?: string | null;
    is_locked?: boolean;
    created_at?: string;
    updated_at?: string;
  };
  fields: Array<{
    id: number;
    label: string;
    data_type: string;
    is_required: boolean;
    help_text?: string;
    options?: string[] | null;
    options_meta?: Array<{
      label: string;
      value?: string | null;
      requires_qty?: boolean;
      qty_label?: string | null;
      min_qty?: number | null;
      max_qty?: number | null;
      step?: number | null;
      default_qty?: number | null;
      unit?: string | null;
      requires_text?: boolean;
      text_label?: string | null;
    }> | null;
  }>;
  open: boolean;
  onClose: () => void;
}

const ViewFormModal: React.FC<ViewFormModalProps> = ({ form, fields, open, onClose }) => {


  const requiredCount = fields.filter((f) => f.is_required).length;
  const optionalCount = fields.length - requiredCount;

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-hidden flex flex-col p-0">
        {/* Header */}
        <DialogHeader className="px-6 pt-6 pb-4 border-b bg-muted/50">
          <div className="flex items-start justify-between gap-4">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-3 mb-2">
                <Eye className="w-5 h-5 text-muted-foreground flex-shrink-0" aria-hidden="true" />
                <DialogTitle className="text-xl font-bold text-foreground truncate">
                  {form.form_name}
                </DialogTitle>
              </div>
              {form.description && (
                <p className="text-sm text-muted-foreground leading-relaxed mt-2">
                  {form.description}
                </p>
              )}
            </div>
          </div>

          {/* Meta Information */}
          <div className="flex items-center gap-2 flex-wrap mt-4">
            <Badge
              variant="outline"
              className="text-xs font-mono bg-background border-border"
            >
              {form.form_code}
            </Badge>
            <Badge
              variant="outline"
              className="text-xs bg-background border-border"
            >
              v{form.version}
            </Badge>
            <Badge
              variant={form.status === "Active" ? "default" : "secondary"}
              className="text-xs"
            >
              {form.status}
            </Badge>
            {form.category_name && (
              <Badge
                variant="outline"
                className="text-xs bg-background border-border"
              >
                {form.category_name}
              </Badge>
            )}
            {form.is_locked && (
              <Badge
                variant="outline"
                className="text-xs bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-800"
              >
                <Lock className="w-3 h-3 mr-1" aria-hidden="true" />
                Locked
              </Badge>
            )}
          </div>

          {/* Statistics */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
            <div className="flex items-center gap-3 p-3 rounded-lg bg-background border border-border">
              <FileText className="w-5 h-5 text-muted-foreground" aria-hidden="true" />
              <div>
                <div className="text-lg font-bold text-foreground">
                  {fields.length}
                </div>
                <div className="text-xs text-muted-foreground uppercase tracking-wide">Fields</div>
              </div>
            </div>
            <div className="flex items-center gap-3 p-3 rounded-lg bg-background border border-border">
              <Asterisk className="w-5 h-5 text-destructive" aria-hidden="true" />
              <div>
                <div className="text-lg font-bold text-foreground">
                  {requiredCount}
                </div>
                <div className="text-xs text-muted-foreground uppercase tracking-wide">Required</div>
              </div>
            </div>
            <div className="flex items-center gap-3 p-3 rounded-lg bg-background border border-border">
              <MinusCircle className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
              <div>
                <div className="text-lg font-bold text-foreground">
                  {optionalCount}
                </div>
                <div className="text-xs text-muted-foreground uppercase tracking-wide">Optional</div>
              </div>
            </div>
          </div>
        </DialogHeader>

        {/* Scrollable Form Fields */}
        <div className="flex-1 overflow-y-auto px-6 py-6">
          {fields.length === 0 ? (
            <div className="text-center py-16">
              <FileText className="w-16 h-16 mx-auto text-muted-foreground/30 mb-4" aria-hidden="true" />
              <p className="text-muted-foreground font-medium">
                No fields configured for this form
              </p>
            </div>
          ) : (
            <FormRenderer
              fields={fields.map((field) => ({
                ...field,
                field_name: `field_${field.id}`,
                field_order: 0,
                placeholder: '',
              }))}
              mode="preview"
            />
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t bg-muted/50">
          <div className="flex justify-between items-center">
            <p className="text-xs text-muted-foreground">
              Preview mode - Fields are not editable
            </p>
            <Button onClick={onClose} variant="default">
              Close Preview
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
};

export default ViewFormModal;
