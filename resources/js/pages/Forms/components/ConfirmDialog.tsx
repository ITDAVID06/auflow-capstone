import React from "react";
import { Button } from "@/components/ui/button";

export type ConfirmDialogProps = {
  open: boolean;
  title?: string;
  description?: string;
  onOpenChange: (v: boolean) => void;
  onConfirm: () => void;
  confirmLabel?: string;
  cancelLabel?: string;
  loading?: boolean;
  children: React.ReactNode;
};

const ConfirmDialog: React.FC<ConfirmDialogProps> = ({
  open,
  title = "Review your submission",
  description = "Please confirm the details below. You can go back to make changes if needed.",
  onOpenChange,
  onConfirm,
  confirmLabel = "Confirm & Submit",
  cancelLabel = "Go Back",
  loading,
  children,
}) => {
  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center"
      aria-modal="true"
      role="dialog"
    >
      {/* backdrop */}
      <div className="absolute inset-0 bg-black/50 backdrop-blur-[1px]" onClick={() => onOpenChange(false)} />

      {/* modal */}
      <div className="relative z-[101] w-[96vw] max-w-5xl rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
        <div className="px-5 pt-4 pb-3 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
          <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>
        </div>

        {/* body */}
        <div className="max-h-[72vh] overflow-y-auto p-4 sm:p-5">{children}</div>

        {/* footer */}
        <div className="flex items-center justify-end gap-2 border-t border-gray-200 dark:border-gray-700 px-5 py-3">
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={loading}
          >
            {cancelLabel}
          </Button>
          <Button 
            type="button" 
            onClick={onConfirm} 
            disabled={loading}
            className="bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-700"
          >
            {loading ? "Submitting…" : confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default ConfirmDialog;