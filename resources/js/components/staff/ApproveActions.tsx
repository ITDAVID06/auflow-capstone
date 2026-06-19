import React, { useState } from "react";
import axios from "axios";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import SnapshotQRDialog from "@/components/snapshots/SnapshotQRDialog";

type Props = {
  progressId: number;
  approveUrl: string; // route('staff-dashboard.progress.approve', { id: progressId })
  snapshotUrl: string; // route('staff-dashboard.progress.snapshot', { id: progressId })
  onDone?: () => void; // optional: refetch parent page
};

export default function ApproveActions({ progressId, approveUrl, snapshotUrl, onDone }: Props) {
  const [loading, setLoading] = useState(false);
  const [qrOpen, setQrOpen] = useState(false);
  const [snapshot, setSnapshot] = useState<null | {
    url: string;
    public_id: string;
    short_code: string;
    status: string;
    approved_at?: string | null;
  }>(null);

  const approve = async () => {
    try {
      setLoading(true);
      // 1) Approve via JSON (Controller supports expectsJson)
      await axios.put(approveUrl, { comment: "" }, { headers: { "X-Requested-With": "XMLHttpRequest" } });

      // 2) Fetch latest snapshot
      const { data } = await axios.get(snapshotUrl, { headers: { "X-Requested-With": "XMLHttpRequest" } });
      if (data?.exists) {
        setSnapshot({
          url: data.url,
          public_id: data.public_id,
          short_code: data.short_code,
          status: data.status,
          approved_at: data.approved_at,
        });
        toast.success("Approved • Snapshot created");
        setQrOpen(true);
      } else {
        toast.success("Approved • Snapshot creation pending");
      }

      onDone?.();
    } catch (e: any) {
      toast.error(e?.response?.data?.error ?? "Approval failed");
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <div className="flex gap-2">
        <Button disabled={loading} onClick={approve}>
          {loading ? "Approving..." : "Approve"}
        </Button>
        {snapshot ? (
          <>
            <Button variant="outline" onClick={() => window.open(snapshot.url, "_blank")}>
              View Snapshot
            </Button>
            <Button variant="secondary" onClick={() => setQrOpen(true)}>
              Show QR
            </Button>
          </>
        ) : null}
      </div>

      <SnapshotQRDialog
        open={qrOpen}
        onOpenChange={setQrOpen}
        url={snapshot?.url ?? ""}
        shortCode={snapshot?.short_code}
        status={snapshot?.status}
      />
    </>
  );
}
