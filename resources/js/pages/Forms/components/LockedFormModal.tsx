import React from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";

interface LockedFormModalProps {
  form: {
    form_name: string;
    form_code: string;
    description: string;
    version: number | string;
    status: string;
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
  onClose: () => void;
}

const LockedFormModal: React.FC<LockedFormModalProps> = ({ form, fields, onClose }) => {
  const renderField = (field: typeof fields[0]) => {
    const baseClasses = "bg-muted/30 cursor-not-allowed";
    
    switch (field.data_type) {
      case "text":
      case "email":
      case "number":
        return (
          <Input
            type={field.data_type}
            disabled
            placeholder={`Sample ${field.data_type} input`}
            className={baseClasses}
          />
        );

      case "textarea":
        return (
          <Textarea
            disabled
            placeholder="Sample text area input"
            className={baseClasses}
            rows={4}
          />
        );

      case "date":
        return (
          <Input
            type="date"
            disabled
            className={baseClasses}
          />
        );

      case "select":
        return (
          <div className={`border rounded-md px-3 py-2 text-sm ${baseClasses}`}>
            <div className="text-muted-foreground">Select options:</div>
            <div className="mt-2 space-y-1">
              {field.options?.map((option, idx) => (
                <div key={idx} className="text-sm pl-2">• {option}</div>
              )) || <div className="text-sm text-muted-foreground italic">No options defined</div>}
            </div>
          </div>
        );

      case "radio":
        return (
          <div className="space-y-2">
            {field.options?.map((option, idx) => (
              <div key={idx} className="flex items-center gap-2">
                <input
                  type="radio"
                  disabled
                  className="cursor-not-allowed"
                />
                <span className="text-sm">{option}</span>
              </div>
            )) || <div className="text-sm text-muted-foreground italic">No options defined</div>}
            
            {field.options_meta?.some(o => o.requires_qty || o.requires_text) && (
              <div className="mt-3 p-3 bg-blue-50/50 dark:bg-blue-950/20 rounded-md border border-blue-200/30 dark:border-blue-800/30">
                <p className="text-xs font-medium text-blue-700 dark:text-blue-400 mb-2">Additional Fields:</p>
                {field.options_meta?.filter(o => o.requires_qty || o.requires_text).map((meta, idx) => (
                  <div key={idx} className="text-xs text-muted-foreground space-y-1">
                    {meta.requires_qty && (
                      <div>• Quantity input for "{meta.label}" ({meta.qty_label || "Qty"})</div>
                    )}
                    {meta.requires_text && (
                      <div>• Text input for "{meta.label}" ({meta.text_label || "Specify"})</div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        );

      case "checkbox":
        return (
          <div className="space-y-2">
            {field.options?.map((option, idx) => (
              <div key={idx} className="flex items-center gap-2">
                <Checkbox
                  disabled
                  className="cursor-not-allowed"
                />
                <span className="text-sm">{option}</span>
              </div>
            )) || <div className="text-sm text-muted-foreground italic">No options defined</div>}
            
            {field.options_meta?.some(o => o.requires_qty || o.requires_text) && (
              <div className="mt-3 p-3 bg-blue-50/50 dark:bg-blue-950/20 rounded-md border border-blue-200/30 dark:border-blue-800/30">
                <p className="text-xs font-medium text-blue-700 dark:text-blue-400 mb-2">Additional Fields:</p>
                {field.options_meta?.filter(o => o.requires_qty || o.requires_text).map((meta, idx) => (
                  <div key={idx} className="text-xs text-muted-foreground space-y-1">
                    {meta.requires_qty && (
                      <div>• Quantity input for "{meta.label}" ({meta.qty_label || "Qty"})</div>
                    )}
                    {meta.requires_text && (
                      <div>• Text input for "{meta.label}" ({meta.text_label || "Specify"})</div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        );

      case "file":
        return (
          <div className={`border-2 border-dashed rounded-md px-4 py-8 text-center ${baseClasses}`}>
            <div className="text-sm text-muted-foreground">File upload field</div>
            <div className="text-xs text-muted-foreground mt-1">Accepts: .jpg, .jpeg, .png, .pdf, .doc, .docx</div>
          </div>
        );

      default:
        return (
          <Input
            type="text"
            disabled
            placeholder={`${field.data_type} field`}
            className={baseClasses}
          />
        );
    }
  };

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <DialogHeader className="border-b pb-4">
          <DialogTitle className="text-xl font-semibold flex items-center gap-3">
            {form.form_name}
            <Badge variant="outline" className="font-mono text-xs">{form.form_code}</Badge>
          </DialogTitle>
          {form.description && (
            <p className="text-sm text-muted-foreground mt-2">{form.description}</p>
          )}
          <div className="flex items-center gap-2 mt-3">
            <Badge variant="secondary" className="text-xs">v{form.version}</Badge>
            <Badge 
              variant={form.status === "Active" ? "default" : "secondary"}
              className="text-xs"
            >
              {form.status}
            </Badge>
            <Badge variant="outline" className="text-xs bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-300 dark:border-amber-800">
              Read-Only Preview
            </Badge>
          </div>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto py-6">
          <div className="space-y-6 px-1">
            {/* Form Fields Section */}
            <div className="space-y-4">
              <div className="pb-2 border-b">
                <h3 className="text-sm font-semibold text-foreground">Form Fields</h3>
                <p className="text-xs text-muted-foreground mt-1">
                  This is a preview of how the form will appear to users. All fields are disabled.
                </p>
              </div>

              {fields.map((field, index) => (
                <div 
                  key={field.id} 
                  className="space-y-2 p-4 rounded-lg border bg-card hover:bg-muted/30 transition-colors"
                >
                  {/* Field Header */}
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="text-xs font-medium text-muted-foreground">
                          #{index + 1}
                        </span>
                        <Label className="text-sm font-semibold">
                          {field.label}
                          {field.is_required && (
                            <span className="text-red-500 ml-1">*</span>
                          )}
                        </Label>
                      </div>
                      
                      {field.help_text && (
                        <p className="text-xs text-muted-foreground italic mt-1">
                          💡 {field.help_text}
                        </p>
                      )}
                    </div>

                    <div className="flex items-center gap-2">
                      <Badge 
                        variant="outline" 
                        className="text-xs capitalize shrink-0"
                      >
                        {field.data_type.replace('_', ' ')}
                      </Badge>
                      {field.is_required && (
                        <Badge variant="destructive" className="text-xs shrink-0">
                          Required
                        </Badge>
                      )}
                    </div>
                  </div>

                  {/* Field Input Preview */}
                  <div className="mt-3">
                    {renderField(field)}
                  </div>

                  {/* Field Metadata */}
                  {(field.data_type === 'number' || field.data_type === 'text' || field.data_type === 'email') && (
                    <div className="mt-2 text-xs text-muted-foreground">
                      <span className="italic">Preview only - actual validation applies on submission</span>
                    </div>
                  )}
                </div>
              ))}
            </div>

            {/* Summary Footer */}
            <div className="mt-6 p-4 bg-blue-50/50 dark:bg-blue-950/20 rounded-lg border border-blue-200/40 dark:border-blue-800/40">
              <div className="flex items-start gap-3">
                <div className="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 border border-blue-200/50 dark:border-blue-800/50 flex items-center justify-center">
                  <svg className="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div className="flex-1">
                  <h4 className="text-sm font-semibold text-foreground">Form Summary</h4>
                  <div className="mt-2 space-y-1 text-xs text-muted-foreground">
                    <div>• Total Fields: {fields.length}</div>
                    <div>• Required Fields: {fields.filter(f => f.is_required).length}</div>
                    <div>• Optional Fields: {fields.filter(f => !f.is_required).length}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="border-t pt-4 flex justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-sm"
          >
            Close Preview
          </button>
        </div>
      </DialogContent>
    </Dialog>
  );
};

export default LockedFormModal;