import React from "react";
import QRCode from "react-qr-code";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { ExternalLink, Printer, Copy, Check } from "lucide-react";
import { toast } from "sonner";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  url: string;
  shortCode?: string;
  status?: string;
};

export default function SnapshotQRDialog({ open, onOpenChange, url, shortCode, status }: Props) {
  const [copied, setCopied] = React.useState(false);

  const copyToClipboard = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      toast.success("Link copied to clipboard");
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast.error("Failed to copy link");
    }
  };

  const isApproved = status === "Approved";

  return (
    <>
      <style>{`
        @media print {
          /* Hide everything on the page */
          body * {
            visibility: hidden !important;
          }

          /* Show only the print QR container */
          .print-qr-container {
            display: block !important;
            visibility: visible !important;
            position: fixed !important;
            left: 50% !important;
            top: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 99999 !important;
            page-break-inside: avoid !important;
          }

          .print-qr-container * {
            visibility: visible !important;
          }
        }

        /* Hide print container in normal view */
        .print-qr-container {
          display: none;
        }

        @media print {
          .print-qr-container {
            display: block !important;
          }
        }
      `}</style>

      {/* QR Code container for printing - rendered outside dialog */}
      <div className="print-qr-container bg-white p-8 rounded-lg border-2 border-border shadow-sm">
        <QRCode value={url} size={256} />
      </div>

      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="sm:max-w-lg md:max-w-xl">
          <DialogHeader>
            <DialogTitle className="text-center">Verification Snapshot QR Code</DialogTitle>
          </DialogHeader>

          <div className="flex flex-col items-center gap-4 py-4">
            {/* Status Badge */}
            <div
              className={`
                inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold
                ${isApproved
                  ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30"
                  : "bg-red-500/10 text-red-700 dark:text-red-400 border border-red-500/30"
                }
              `}
            >
              {isApproved ? (
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              ) : (
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              )}
              {status ?? "Unknown"}
            </div>

            {/* QR Code - visible in dialog */}
            <div className="bg-white p-8 rounded-lg border-2 border-border shadow-sm">
              <QRCode value={url} size={256} />
            </div>

            {/* Action Buttons */}
            <div className="flex gap-2 w-full mt-2">
              <Button
                variant="outline"
                className="flex-1"
                onClick={() => window.open(url, "_blank")}
              >
                <ExternalLink className="mr-2 h-4 w-4" />
                Open Link
              </Button>
              <Button
                variant="outline"
                className="flex-1"
                onClick={() => window.print()}
              >
                <Printer className="mr-2 h-4 w-4" />
                Print
              </Button>
            </div>

            {/* Close Button */}
            <Button
              onClick={() => onOpenChange(false)}
              className="w-full bg-[#1551f1] hover:bg-[#5296ea] text-white"
            >
              Close
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}