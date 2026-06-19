import { useStaffActions } from "../hooks/useStaffActions";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Eye, FileText } from "lucide-react";
import { Link, usePage } from "@inertiajs/react";
import { getStatusBadge } from "@/lib/statusBadge";
import { Badge } from "@/components/ui/badge";
import { useMemo, useState } from "react";
import RejectModal from "./RejectModal";
import ApproveModal from "./ApproveModal";
import EmptyState from "@/components/EmptyState";
import { type SharedData } from "@/types";

type Row = {
  progress_id: number;     // workflow step progress id
  submission_id: number;   // form submission id
  form_code: string;
  form_name: string;
  status: "Pending" | "Approved" | "Rejected" | string;
  submitted_at: string;
  submitter: string;
  version?: number;        // version number from history
  is_latest?: boolean;     // flag to know if this is the latest revision
};

type RequestsProp =
  | Row[]                                   // non-paginated
  | { data: Row[]; meta?: Record<string, any> }; // paginated

export default function RequestsTable({ requests }: { requests: RequestsProp }) {
  const { auth } = usePage<SharedData>().props;
  const permissions = auth?.user?.permissions ?? [];
  const canViewSubmission = permissions.some((permission) =>
    ["submissions.view", "requests.approve", "submissions.override"].includes(permission)
  );
  const canReviewSubmission = permissions.some((permission) =>
    ["requests.approve", "submissions.override"].includes(permission)
  );

  const rows: Row[] = useMemo(() => {
    let data: Row[] = [];
    if (Array.isArray(requests)) data = requests;
    else if (requests && typeof requests === "object" && Array.isArray((requests as any).data)) {
      data = (requests as any).data;
    }
    // only show latest revisions
    return data.filter((r) => r.is_latest ?? true);
  }, [requests]);

  const { actOnSubmission } = useStaffActions();
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [selectedProgressId, setSelectedProgressId] = useState<number | null>(null);
  const [showApproveModal, setShowApproveModal] = useState(false);
  const [selectedApproveId, setSelectedApproveId] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Pending Approvals</CardTitle>
          <p className="text-sm text-muted-foreground">
            Document requests assigned to you for review
          </p>
        </CardHeader>

        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-left border-b">
              <tr className="text-muted-foreground">
                <th className="py-2">Form Code</th>
                <th className="py-2">Title</th>
                <th className="py-2">Requester</th>
                <th className="py-2">Status</th>
                <th className="py-2">Submitted</th>
                <th className="py-2 text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-12">
                    <EmptyState
                      icon={<FileText className="h-6 w-6" />}
                      title="No requests pending"
                      message="You have no document requests assigned to you for review at this time."
                    />
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={r.progress_id} className="border-b last:border-0 hover:bg-muted/50 transition">
                    <td className="py-3">
                      <Badge variant="outline" className="text-xs px-2 py-1">
                        {r.form_code || "-"}
                      </Badge>
                    </td>
                    <td className="py-3">
                      {r.form_name}
                      {r.version && (
                        <Badge variant="outline" className="ml-2 text-xs">
                          v{r.version}
                        </Badge>
                      )}
                      {!r.is_latest && (
                        <Badge variant="destructive" className="ml-1 text-xs">
                          Old
                        </Badge>
                      )}
                    </td>
                    <td className="py-3">{r.submitter}</td>
                    <td className="py-3">
                      <div className="flex gap-1">
                        {getStatusBadge(r.status)}
                        {r.status === "Rejected" && (
                          <Badge variant="outline" className="text-yellow-700 border-yellow-500">
                            Revision
                          </Badge>
                        )}
                      </div>
                    </td>
                    <td className="py-3">
                      {r.submitted_at ? new Date(r.submitted_at).toLocaleDateString() : "-"}
                    </td>
                    <td className="py-3 text-center">
                      <div className="flex gap-2 justify-center items-center">
                        {canViewSubmission && (
                          <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="hover:bg-muted rounded-md"
                          >
                            <Link href={`/staff-dashboard/submission/${r.progress_id}`}>
                              <Eye className="w-4 h-4 text-muted-foreground" />
                            </Link>
                          </Button>
                        )}

                        {canReviewSubmission && r.status === "Pending" && (
                          <>
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              className="border-red-600 text-red-600 hover:bg-red-50"
                              disabled={busy}
                              onClick={() => {
                                setSelectedProgressId(r.progress_id);
                                setShowRejectModal(true);
                              }}
                            >
                              Reject
                            </Button>
                            <Button
                              type="button"
                              size="sm"
                              className="bg-green-600 text-white hover:bg-green-700"
                              disabled={busy}
                              onClick={() => {
                                setSelectedApproveId(r.progress_id);
                                setShowApproveModal(true);
                              }}
                            >
                              Approve
                            </Button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>

      {selectedProgressId !== null && (
        <RejectModal
  open={showRejectModal}
  onOpenChange={setShowRejectModal}
  progressId={selectedProgressId!}
  onReject={async (progressId, comment, files) => {
    setBusy(true);
    try {
      await actOnSubmission(progressId, "reject", comment, files);
    } finally {
      setBusy(false);
    }
  }}
/>
      )}

      {selectedApproveId !== null && (
        <ApproveModal
  open={showApproveModal}
  onOpenChange={setShowApproveModal}
  progressId={selectedApproveId!}
  onApprove={async (progressId, comment, files) => {
    setBusy(true);
    try {
      await actOnSubmission(progressId, "approve", comment, files);
    } finally {
      setBusy(false);
    }
  }}
/>
      )}
    </>
  );
}
