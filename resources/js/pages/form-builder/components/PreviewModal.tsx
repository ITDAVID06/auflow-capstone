import React, { useMemo, useState } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Monitor, Smartphone, Plus, FileText } from "lucide-react";
import { FormBuilderState } from "../types/formBuilderTypes";
import { FormRenderer } from "@/components/forms/FormRenderer";
import logoUrl from "@/assets/auf_logo.png";

type DeviceMode = "desktop" | "mobile";

export function PreviewModal({
  form,
  onClose,
}: {
  form: FormBuilderState;
  onClose: () => void;
}) {
  const [deviceMode, setDeviceMode] = useState<DeviceMode>("desktop");
  
  const sortedFields = useMemo(
    () => [...form.fields].sort((a, b) => a.field_order - b.field_order),
    [form.fields],
  )

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent
        className="flex h-[90vh] w-[95vw] max-w-[1240px] flex-col overflow-hidden rounded-xl border border-border/70 bg-card p-0 shadow-2xl sm:max-w-[95vw]"
        hideClose
      >
        {/* Header with Device Toggle */}
        <DialogHeader className="border-b border-border/70 bg-background/95 px-5 pb-3 pt-4 backdrop-blur">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="space-y-0.5">
              <DialogTitle className="text-lg font-semibold text-foreground">Form Preview</DialogTitle>
              <p className="text-xs text-muted-foreground">
                {deviceMode === "desktop" ? "Desktop view" : "Mobile view"}
              </p>
            </div>

            {/* Device Mode Toggle */}
            <div className="flex items-center gap-2">
              <div className="flex items-center gap-1 rounded-md border border-border/70 bg-background p-1 shadow-sm">
                <Button
                  variant={deviceMode === "desktop" ? "secondary" : "ghost"}
                  size="sm"
                  onClick={() => setDeviceMode("desktop")}
                  className="h-8 rounded-md px-3 text-xs"
                >
                  <Monitor className="mr-1.5 h-3.5 w-3.5" />
                  Desktop
                </Button>
                <Button
                  variant={deviceMode === "mobile" ? "secondary" : "ghost"}
                  size="sm"
                  onClick={() => setDeviceMode("mobile")}
                  className="h-8 rounded-md px-3 text-xs"
                >
                  <Smartphone className="mr-1.5 h-3.5 w-3.5" />
                  Mobile
                </Button>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={onClose}
                className="h-8 rounded-md px-3 text-xs"
              >
                Close Preview
              </Button>
            </div>
          </div>
        </DialogHeader>

        {/* Simulated Browser Window */}
        <div className="flex-1 overflow-y-auto bg-muted/50 p-3 sm:p-4 dark:bg-zinc-900/50">
          <div
            className={[
              "mx-auto transition-[width] duration-300",
              deviceMode === "desktop" ? "w-[88%] max-w-6xl" : "w-[375px] max-w-full",
            ].join(" ")}
          >
            <div
              className={[
                "overflow-hidden rounded-xl border border-border/70 bg-white shadow-sm dark:bg-zinc-950",
                deviceMode === "desktop"
                  ? ""
                  : "mx-auto min-h-[760px] max-h-[82vh] w-[375px] max-w-full rounded-[1.25rem] shadow-lg",
              ].join(" ")}
            >
              <div
                className={[
                  "grid grid-cols-12",
                  deviceMode === "desktop" ? "gap-x-6 gap-y-5 px-8 py-6" : "gap-x-4 gap-y-5 p-4",
                ].join(" ")}
              >
                <section className="col-span-12 border-b border-border/30 pb-4 dark:border-zinc-800/70">
                  <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                      <img
                        src={logoUrl}
                        alt="AUF Logo"
                        width={44}
                        height={44}
                        className="h-11 w-11 rounded-md object-contain ring-1 ring-border/70"
                      />
                      <div className="space-y-0.5">
                        <p className="text-sm font-semibold text-foreground">Angeles University Foundation</p>
                        <p className="text-xs text-muted-foreground">Digital Document Management System</p>
                      </div>
                    </div>
                    <div className={[
                      "text-right",
                      deviceMode === "desktop" ? "block" : "hidden",
                    ].join(" ")}>
                      <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Official Document Preview
                      </p>
                      <p className="text-[11px] text-muted-foreground/80">AUFlow Request Form</p>
                    </div>
                  </div>
                </section>

                <section className="col-span-12 space-y-2">
                  <h1
                    className={[
                      "font-bold leading-tight text-foreground",
                      deviceMode === "desktop" ? "text-2xl" : "text-xl",
                    ].join(" ")}
                  >
                  {form.form_name || "Untitled Form"}
                  </h1>
                  {form.description && (
                    <p className="max-w-3xl text-sm leading-relaxed text-muted-foreground">{form.description}</p>
                  )}
                </section>

                {/* Form content using shared FormRenderer */}
                <form className="col-span-12 space-y-6">
                  {/* All fields including date fields */}
                  <FormRenderer
                    fields={sortedFields}
                    mode="preview"
                  />

                  {/* Attachments Section */}
                  <section className="space-y-3 border-b border-gray-100 pb-5 pt-1 dark:border-zinc-800/70">
                    <div className="space-y-1.5">
                      <h2 className="text-base font-semibold text-foreground">Attachments (Optional)</h2>
                      <p className="text-sm text-muted-foreground">
                        Upload supporting documents. You can select multiple files at once.
                      </p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        Accepted formats: JPG, JPEG, PNG, PDF, DOC, DOCX (Max 10MB per file)
                      </p>
                    </div>
                    <div className="space-y-3">
                      <div className="flex flex-col gap-2">
                        <label className="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-md border border-blue-200/70 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 opacity-60 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300 sm:w-auto">
                          <Plus className="h-4 w-4" />
                          Choose Files to Upload
                        </label>
                        <p className="text-xs text-muted-foreground">
                          Files are not uploaded until you submit this request.
                        </p>
                      </div>
                      <div className="rounded-lg border-2 border-dashed border-border/70 bg-muted/20 p-5 text-center">
                        <FileText className="mx-auto mb-2 h-7 w-7 text-muted-foreground/70" />
                        <p className="text-sm font-medium text-foreground/90">No attachments yet</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                          Click &ldquo;Choose Files&rdquo; above to add documents
                        </p>
                      </div>
                    </div>
                  </section>

                  <div className="mt-8">
                    <Button
                      disabled
                      className="h-11 w-full"
                    >
                      Submit Request
                    </Button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
