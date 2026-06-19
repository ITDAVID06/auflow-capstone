import { useCallback, useEffect, useState } from "react";
import { toast } from "sonner";
import { AsyncExportStatus } from "../types";

export interface UseAsyncExportReturn {
  asyncExport: AsyncExportStatus | null;
  setAsyncExport: React.Dispatch<React.SetStateAction<AsyncExportStatus | null>>;
  retryExport: (triggerExport: () => void) => void;
}

export function useAsyncExport(): UseAsyncExportReturn {
  const [asyncExport, setAsyncExport] = useState<AsyncExportStatus | null>(null);

  const pollAsyncExport = useCallback(async (exportId: string) => {
    try {
      const response = await fetch(route("reports.exports.status", { exportId }), {
        method: "GET",
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });

      if (!response.ok) return;

      const payload = (await response.json()) as AsyncExportStatus;
      setAsyncExport(payload);

      if (payload.status === "completed") {
        toast.success("Export is ready for download.");
      }

      if (payload.status === "failed") {
        toast.error(payload.error || "Export failed.");
      }
    } catch {
      // Ignore transient polling errors.
    }
  }, []);

  useEffect(() => {
    if (!asyncExport || (asyncExport.status !== "queued" && asyncExport.status !== "processing")) {
      return;
    }

    const intervalId = window.setInterval(() => {
      void pollAsyncExport(asyncExport.export_id);
    }, 2500);

    void pollAsyncExport(asyncExport.export_id);

    return () => {
      window.clearInterval(intervalId);
    };
  }, [asyncExport, pollAsyncExport]);

  const retryExport = useCallback(
    (triggerExport: () => void) => {
      setAsyncExport(null);
      triggerExport();
    },
    [],
  );

  return { asyncExport, setAsyncExport, retryExport };
}
