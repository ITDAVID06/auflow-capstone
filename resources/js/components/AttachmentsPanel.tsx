import React from "react";
import { Button } from "@/components/ui/button";
import { Paperclip } from "lucide-react";

interface Attachment {
  id: number;
  original_name: string;
  mime_type?: string;
  file_path: string;
}

interface Props {
  attachments: Attachment[];
  downloadRoute?: string; // e.g. route('attachments.download', id)
  canUpload?: boolean;
  onUpload?: (files: FileList) => void;
  canDelete?: boolean;
  onDelete?: (id: number) => void;
}

const AttachmentsPanel: React.FC<Props> = ({
  attachments,
  downloadRoute,
  canUpload = false,
  onUpload,
  canDelete = false,
  onDelete,
}) => {
  return (
    <div className="border rounded-lg p-4 space-y-3">
      <h3 className="font-semibold flex items-center gap-2">
        <Paperclip className="w-4 h-4" />
        Attachments
      </h3>

      {/* Upload */}
      {canUpload && (
        <div>
          <input
            type="file"
            multiple
            onChange={(e) => {
              if (e.target.files && onUpload) {
                onUpload(e.target.files);
              }
            }}
          />
        </div>
      )}

      {/* Attachment List */}
      {attachments?.length ? (
        <ul className="divide-y text-sm">
          {attachments.map((a) => (
            <li key={a.id} className="flex justify-between items-center py-2">
              <span>{a.original_name}</span>
              <div className="flex gap-2">
                {downloadRoute && (
                  <a
                    href={downloadRoute.replace(":id", String(a.id))}
                    className="text-blue-600 hover:underline"
                  >
                    Download
                  </a>
                )}
                {canDelete && (
                  <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => onDelete?.(a.id)}
                  >
                    Delete
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-muted-foreground text-sm">No attachments</p>
      )}
    </div>
  );
};

export default AttachmentsPanel;
