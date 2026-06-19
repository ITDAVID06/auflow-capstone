import React, { useMemo, useState } from "react";
import { Head, usePage } from "@inertiajs/react";
import QRCode from "react-qr-code";
import {
  Printer,
  CheckCircle2,
  AlertTriangle,
  Download,
  Eye,
  ExternalLink,
  User,
  Calendar,
  Lock,
  FileText,
  ImageIcon,
  Copy,
  Check,
} from "lucide-react";
import FileViewerDialog from "@/components/FileViewerDialog";
import { StatusPill, statusTone } from "@/components/snapshots/StatusPill";
import { Watermark } from "@/components/snapshots/Watermark";
import type { SnapshotProp, ApprovalRecord, Field, ApprovalItem } from "@/components/snapshots/SnapshotTypes";
import logoUrl from "@/assets/auf_logo.png";

/* ---------- Helpers ---------- */
const fmt = (d?: string | null) => (d && !isNaN(Date.parse(d)) ? new Date(d).toLocaleString() : "—");

function toDisplay(value: unknown): string {
  if (value === null || value === undefined) {
    return "—";
  }

  const text = String(value).trim();
  if (text === "" || text.toLowerCase() === "null" || text.toLowerCase() === "undefined") {
    return "—";
  }

  return text;
}

/** Try parsing up to 2 levels to handle double-encoded JSON */
function tryParseJSONDeep(v?: unknown): unknown | null {
  if (typeof v !== "string") return null;
  let s: unknown = v.trim();
  if (typeof s !== "string" || (!s.startsWith("{") && !s.startsWith("["))) return null;

  for (let i = 0; i < 2; i++) {
    if (typeof s === "string") {
      try {
        s = JSON.parse(s);
      } catch {
        return i === 0 ? null : s;
      }
    } else {
      break;
    }
  }
  return s;
}

function formatSlots(v: unknown): string {
  const toLine = (x: any) => {
    const date = x?.date ? new Date(x.date).toLocaleDateString() : (x?.date ?? "—");
    const start = x?.start_time ?? "—";
    const end = x?.end_time ?? "—";
    const venue = x?.venue ? ` @ ${x.venue}` : "";
    return `${date} • ${start}-${end}${venue}`;
  };
  if (Array.isArray(v)) return v.map(toLine).join("\n");
  if (v && typeof v === "object") return toLine(v as Record<string, any>);
  return "—";
}

function looksLikeSlotsLabel(label?: string): boolean {
  if (!label) return false;
  const L = label.toLowerCase();
  return L.includes("date and venue") || L.includes("date & venue") || L.includes("schedule") || L.includes("slot");
}

function prettyLabel(raw?: string | null): string {
  if (!raw) return "Field";
  return raw
    .split(/[-_\s]+/)
    .map((w) => (w ? w.charAt(0).toUpperCase() + w.slice(1).toLowerCase() : ""))
    .join(" ")
    .trim();
}

function parseTableData(value: unknown): { isTable: boolean; data: Record<string, unknown>[]; columns: string[] } {
  const parsed = typeof value === "string" ? tryParseJSONDeep(value) : value;
  if (!Array.isArray(parsed) || parsed.length === 0) {
    return { isTable: false, data: [], columns: [] };
  }

  const rows = parsed.filter((row): row is Record<string, unknown> => !!row && typeof row === "object" && !Array.isArray(row));
  if (rows.length === 0) {
    return { isTable: false, data: [], columns: [] };
  }

  const columns = Array.from(new Set(rows.flatMap((row) => Object.keys(row))));
  if (columns.length === 0) {
    return { isTable: false, data: [], columns: [] };
  }

  return { isTable: true, data: rows, columns };
}

function extractMetaLookup(field: Field): Record<string, string> {
  const raw = field.field_options;
  if (!raw || typeof raw !== "object") {
    return {};
  }

  const optionsMeta = (raw as Record<string, unknown>).options_meta;
  if (!Array.isArray(optionsMeta)) {
    return {};
  }

  const entries = optionsMeta
    .filter((item): item is Record<string, unknown> => !!item && typeof item === "object")
    .map((item) => {
      const value = String(item.value ?? item.label ?? "").trim();
      const label = String(item.label ?? item.value ?? "").trim();
      return [value, label] as const;
    })
    .filter(([value]) => value !== "");

  return Object.fromEntries(entries);
}

/** Compact number rendering */
const toCompactNumber = (val: string | number): string => {
  const n = typeof val === "number" ? val : Number(String(val).trim());
  if (!Number.isFinite(n)) return String(val);
  const asInt = Math.trunc(n);
  return Math.abs(n - asInt) < 1e-9 ? String(asInt) : String(n);
};

/** Enhanced renderer for meta fields with qty AND text support */
function renderMetaQuantityDisplay(parsed: unknown, lookup: Record<string, string> = {}): string {
  // Helper to humanize values (remove underscores, capitalize)
  const humanize = (str: string): string => {
    if (!str) return "";
    return str
      .replace(/_/g, " ")           // Replace underscores with spaces
      .replace(/\s+/g, " ")          // Normalize multiple spaces
      .trim()
      .replace(/\b\w/g, (c) => c.toUpperCase()); // Capitalize first letter of each word
  };

  if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
    const o = parsed as Record<string, unknown>;

    // Otherwise show value with quantity (humanized)
    const rawValue = toDisplay(o.value ?? o.Value ?? o.label ?? o.Label ?? o.name ?? o.Name);
    const v = lookup[rawValue] ?? rawValue;
    const q = (o.qty ?? o.Qty) as number | undefined;
    const text = toDisplay(o.text ?? o.Text);
    if (v === "—") return "";

    const humanizedValue = humanize(v);
    const qtyPart = typeof q === "number" ? ` × ${toCompactNumber(q)}` : "";
    const textPart = text !== "—" ? ` — "${text}"` : "";
    return `${humanizedValue}${qtyPart}${textPart}`;
  }
  
  if (Array.isArray(parsed)) {
    const parts = parsed
      .map((item) => {
        if (item && typeof item === "object") {
          const o = item as Record<string, unknown>;

          // Otherwise show value with quantity (humanized)
          const rawValue = toDisplay(o.value ?? o.Value ?? o.label ?? o.Label ?? o.name ?? o.Name);
          const v = lookup[rawValue] ?? rawValue;
          const q = (o.qty ?? o.Qty) as number | undefined;
          const text = toDisplay(o.text ?? o.Text);
          if (v === "—") return "";

          const humanizedValue = humanize(v);
          const qtyPart = typeof q === "number" ? ` × ${toCompactNumber(q)}` : "";
          const textPart = text !== "—" ? ` — "${text}"` : "";
          return `${humanizedValue}${qtyPart}${textPart}`;
        }
        if (typeof item === "number") return toCompactNumber(item);
        if (typeof item === "string") {
          try {
            const again = JSON.parse(item);
            const view = renderMetaQuantityDisplay(again, lookup);
            if (view) return view;
          } catch {}
          return humanize(item);
        }
        return "";
      })
      .filter(Boolean);
    return parts.join(", ");
  }
  return "";
}

/** Shared class for the value "box" */
const fieldBoxClass =
  "rounded-md border border-zinc-200/60 dark:border-zinc-700/50 bg-zinc-50/50 dark:bg-zinc-800/30 " +
  "px-3 py-2 text-[13px] text-zinc-900 dark:text-zinc-100";

/* ---------- Sub-components ---------- */

function SectionHeading({ title, description }: { title: string; description?: string }) {
  return (
    <div className="mb-4 print:mb-1.5">
      <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-100 print:text-[10px]">{title}</h2>
      {description && (
        <p className="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400 print:text-[9px]">{description}</p>
      )}
    </div>
  );
}

function CompactTimeline({ approvals }: { approvals: ApprovalItem[] }) {
  if (!approvals.length) {
    return <p className="text-sm text-zinc-500 dark:text-zinc-400">No approval history recorded yet.</p>;
  }
  return (
    <ol>
      {approvals.map((a, i) => {
        const isLast = i === approvals.length - 1;
        const s = (a.status || "").toLowerCase();
        const dotBg =
          s === "approved" || s === "completed"
            ? "bg-emerald-500"
            : s === "rejected"
            ? "bg-rose-500"
            : "bg-amber-400";
        return (
          <li key={i} className="relative flex gap-3 pb-5 last:pb-0">
            <div className="relative flex flex-col items-center w-3 flex-shrink-0 pt-1">
              <div className={`h-2 w-2 rounded-full ring-2 ring-white dark:ring-zinc-950 z-10 ${dotBg}`} aria-hidden />
              {!isLast && <div className="absolute top-3 bottom-0 w-px bg-zinc-200 dark:bg-zinc-700" aria-hidden />}
            </div>
            <div className="flex-1 min-w-0 pb-1">
              <div className="flex flex-wrap items-center gap-2 mb-1">
                <span className="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                  {toDisplay(a.step)}
                </span>
                <StatusPill status={a.status} />
              </div>
              <div className="space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                  <User className="h-3 w-3 flex-shrink-0" aria-hidden />
                  <span>{toDisplay(a.actor)}</span>
                </div>
                {(a.acted_at || a.created_at) && (
                  <div className="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    <Calendar className="h-3 w-3 flex-shrink-0" aria-hidden />
                    <span>{fmt(a.acted_at || a.created_at)}</span>
                  </div>
                )}
                {a.comment && (
                  <div className="mt-1.5 rounded border border-zinc-200/60 bg-zinc-50/50 dark:border-zinc-700/50 dark:bg-zinc-800/30 px-2.5 py-1.5 text-[12px] text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">
                    {a.comment}
                  </div>
                )}
                {a.action_hash && (
                  <div className="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    <Lock className="h-3 w-3 flex-shrink-0" aria-hidden />
                    <code className="font-mono text-[10px] truncate">{a.action_hash.slice(0, 20)}…</code>
                  </div>
                )}
              </div>
            </div>
          </li>
        );
      })}
    </ol>
  );
}

/** Pretty printer for field values */
function prettyFieldValue(field: Field): React.ReactNode {
  const raw = field.value;
  const type = (field.type || "").toLowerCase();
  const metaLookup = extractMetaLookup(field);

  const parsed: unknown =
    typeof raw === "string"
      ? tryParseJSONDeep(raw)
      : Array.isArray(raw) || typeof raw === "object"
      ? (raw as any)
      : null;

  // 1) Quantity-aware (objects/arrays with {value/label/name, qty, text})
  if (parsed && (Array.isArray(parsed) || typeof parsed === "object")) {
    const qtyView = renderMetaQuantityDisplay(parsed, metaLookup);
    if (qtyView) return <span className="text-zinc-900 dark:text-zinc-100">{qtyView}</span>;
  }

  // 2) Checkbox / multi-select fallback
  if (type.includes("checkbox") || type.includes("multi")) {
    const arr = Array.isArray(parsed) ? parsed : null;
    if (arr && arr.length) return <span className="text-zinc-900 dark:text-zinc-100">{arr.map(String).join(", ")}</span>;
  }

  // 3) Slots / date-venue / generic JSON
  if (
    type.includes("slot") || type.includes("schedule") || type.includes("date_venue") ||
    looksLikeSlotsLabel(field.label) || (parsed && (Array.isArray(parsed) || typeof parsed === "object"))
  ) {
    if (parsed) {
      if (looksLikeSlotsLabel(field.label) || (Array.isArray(parsed) && parsed[0] && (parsed[0] as any).date)) {
        return (
          <pre className="whitespace-pre-wrap text-[13px] leading-relaxed text-zinc-900 dark:text-zinc-100">
            {formatSlots(parsed)}
          </pre>
        );
      }
      if (Array.isArray(parsed)) {
        return <span className="text-zinc-900 dark:text-zinc-100">{parsed.length ? parsed.join(", ") : "—"}</span>;
      }
      const obj = parsed as Record<string, any>;
      const lines = Object.entries(obj).map(([k, v]) => `${k}: ${toDisplay(v)}`);
      return (
        <pre className="whitespace-pre-wrap text-[13px] leading-relaxed text-zinc-900 dark:text-zinc-100">
          {lines.join("\n")}
        </pre>
      );
    }
  }

  // 4) Plain scalars
  if (typeof raw === "number") return <span className="text-zinc-900 dark:text-zinc-100">{toCompactNumber(raw)}</span>;
  if (typeof raw === "string") {
    const s = raw.trim();
    const n = Number(s);
    if (!Number.isNaN(n) && s !== "") return <span className="text-zinc-900 dark:text-zinc-100">{toCompactNumber(n)}</span>;
    return <span className="text-zinc-900 dark:text-zinc-100">{s || "—"}</span>;
  }
  return <span className="text-zinc-900 dark:text-zinc-100">{toDisplay(raw)}</span>;
}

/* ---------- Page ---------- */
interface Attachment {
  id: number;
  filename: string;
  path: string;
  mime_type?: string;
  uploaded_at: string;
}

export default function Show() {
  const { props } = usePage<{
    snapshot: SnapshotProp;
    /** Frozen approval history from the snapshot payload — never from live DB. */
    approval_history: ApprovalRecord[];
    is_workflow_complete: boolean;
    total_steps: number;
    attachments: Attachment[];
  }>();
  const s = props.snapshot;
  // approval_history is the frozen payload array; fall back to empty for legacy snapshots.
  const approvalHistory = props.approval_history || [];
  const attachments = props.attachments || [];
  const isComplete = props.is_workflow_complete ?? false;
  const tone = statusTone(s.status);
  const [pdfViewer, setPdfViewer] = useState<{ url: string; title?: string; mime?: string } | null>(null);
  const [linkCopied, setLinkCopied] = useState(false);

  const verifyUrl = useMemo(() => {
    const origin = typeof window !== "undefined" ? window.location.origin : "";
    return `${origin}/snapshots/${s.public_id}`;
  }, [s.public_id]);

  const copyVerifyUrl = async () => {
    try {
      await navigator.clipboard.writeText(verifyUrl);
      setLinkCopied(true);
      setTimeout(() => setLinkCopied(false), 2000);
    } catch {
      // silent fail — clipboard API may be unavailable
    }
  };

  // Helper functions for file handling
  const isAttachmentValue = (v: unknown) => {
    if (typeof v !== "string") return false;
    const str = v.toLowerCase();
    return (
      str.includes('/storage/') ||
      str.includes('/files/') ||
      str.includes('submission_uploads') ||
      str.includes('submissions_uploads') ||
      str.startsWith('storage/') ||
      str.match(/\.(jpg|jpeg|png|gif|webp|bmp|svg|pdf|doc|docx|xls|xlsx|txt|zip|rar)$/i) !== null
    );
  };
  
  const toPrivateUrl = (p: string) => {
    const raw = (p || "").trim();

    if (raw === "") {
      return "/files/";
    }

    const withoutOrigin = raw.replace(/^https?:\/\/[^/]+/i, "");
    const withoutLeadingSlash = withoutOrigin.replace(/^\/+/, "");

    const normalizedPath = withoutLeadingSlash
      .replace(/^files\//i, "")
      .replace(/^storage\//i, "");

    return `/files/${normalizedPath.split("/").map(encodeURIComponent).join("/")}`;
  };
  
  const extOf = (p: string) =>
    (p || "").split("?")[0].match(/\.([a-z0-9]+)$/i)?.[1]?.toLowerCase() || "";
  
  const isImage = (pOrMime: string) => {
    const ext = extOf(pOrMime);
    return ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"].includes(ext) || /^(image)\/|svg\+xml/i.test(pOrMime);
  };
  
  const isPdfCheck = (pOrMime: string) => {
    return extOf(pOrMime) === "pdf" || /pdf/i.test(pOrMime);
  };
  
  const inferMime = (p: string) => {
    const ext = extOf(p);
    if (ext === "pdf") return "application/pdf";
    if (["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"].includes(ext)) 
      return `image/${ext === "jpg" ? "jpeg" : ext}`;
    return "application/octet-stream";
  };

  const orderedApprovals = useMemo<ApprovalItem[]>(() => {
    const toTs = (v?: string | null) => {
      if (!v) return Number.POSITIVE_INFINITY;
      const t = Date.parse(v);
      return Number.isNaN(t) ? Number.POSITIVE_INFINITY : t;
    };

    // Source: frozen payload — never queries the live DB.
    return approvalHistory
      .map((a, i) => ({ ...a, __idx: i } as any))
      .sort((a: any, b: any) => {
        const ta = toTs(a.acted_at);
        const tb = toTs(b.acted_at);
        if (ta !== tb) return ta - tb;

        const ca = toTs(a.created_at);
        const cb = toTs(b.created_at);
        if (ca !== cb) return ca - cb;

        return a.__idx - b.__idx;
      }) as unknown as ApprovalItem[];
  }, [approvalHistory]);

  return (
    <>
      <Head title={`Verification • ${s.short_code}`} />

      {/* Print-specific styles — @page and comprehensive rules are at the bottom of this component */}
      <style>{`
        @media print {
          body { background: white !important; }
          .print\\:hidden { display: none !important; }
          .no-print-buttons * button,
          .no-print-buttons * a[download],
          .no-print-buttons * .hover\\:opacity-100 { display: none !important; }
        }
      `}</style>

      {/* Outer page container */}
      <div id="print-page" className="mx-auto my-3 w-full max-w-[1200px] px-3 sm:my-6 sm:px-4 print:my-0 print:max-w-full">

        {/* ── Workflow completion banner ── */}
        {isComplete ? (
          <div
            role="status"
            className="mb-4 flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-300 print:mb-2 print:border-emerald-300 print:text-[10px]"
          >
            <CheckCircle2 className="h-5 w-5 flex-shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden />
            <span>
              <strong className="font-semibold">Workflow Complete</strong> — This is the official final record. All required approvals have been captured.
            </span>
          </div>
        ) : (
          <div
            role="alert"
            className="mb-4 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/40 dark:text-amber-300 print:mb-2 print:border-amber-300 print:text-[10px]"
          >
            <AlertTriangle className="h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" aria-hidden />
            <span>
              <strong className="font-semibold">Workflow In Progress — Partial Record</strong>: This snapshot shows an in‑progress workflow and is <em>not</em> a final clearance record.
              {s.step ? <> Current step: <strong>{s.step}</strong>.</> : null}
            </span>
          </div>
        )}

        {/* CERTIFICATE FRAME */}
        <div id="certificate" className="relative overflow-hidden rounded-xl border border-zinc-200/60 bg-white dark:border-zinc-800/60 dark:bg-zinc-950 print:rounded-none print:border-0 print:shadow-none print:overflow-visible">
          {/* Status accent bar */}
          <div
            aria-hidden
            className={`h-0.5 w-full print:hidden ${
              tone.cardBorder.includes("emerald")
                ? "bg-emerald-400"
                : tone.cardBorder.includes("red")
                ? "bg-rose-400"
                : "bg-amber-400"
            }`}
          />
          <Watermark status={s.status} />

          {/* Header Bar */}
          <div className="border-b border-zinc-200/60 bg-white px-4 py-4 sm:px-8 dark:border-zinc-800/60 dark:bg-zinc-950 print:bg-white print:px-3 print:py-1">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
              <div className="flex items-center gap-3 sm:gap-4">
                <div className="h-10 w-10 overflow-hidden rounded-full bg-white p-1.5 shadow-sm dark:bg-slate-900 sm:h-12 sm:w-12 print:h-7 print:w-7 print:p-0.5">
                  <img
                    src={logoUrl}
                    alt="AUF Logo"
                    className="h-full w-full object-contain"
                    draggable={false}
                  />
                </div>
                <div>
                  <div className="text-sm font-bold text-zinc-900 sm:text-base dark:text-zinc-100">
                    Angeles University Foundation
                  </div>
                  <div className="text-xs text-zinc-500 dark:text-zinc-400">Digital Verification Certificate</div>
                </div>
              </div>
              <button
                onClick={() => window.print()}
                className="print:hidden inline-flex w-full items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-transparent px-4 py-2 text-sm font-medium text-zinc-700 motion-safe:transition-colors hover:bg-zinc-50 sm:w-auto dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
              >
                <Printer className="h-4 w-4" aria-hidden />
                Print
              </button>
            </div>
          </div>

          {/* Certificate Info Bar */}
          <div className="flex flex-col gap-3 border-b border-zinc-200/50 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-8 dark:border-zinc-800/50 print:bg-white print:px-3 print:py-1">
            <div className="flex items-center gap-4">
              <div>
                <div className="text-[10px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                  Certificate ID
                </div>
                <div className="text-sm font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                  {toDisplay(s.short_code)}
                </div>
              </div>
              <div className="hidden h-5 w-px bg-zinc-200 sm:block dark:bg-zinc-700/60"></div>
              <div>
                <div className="text-[10px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                  Issued
                </div>
                <div className="text-sm text-zinc-700 dark:text-zinc-300">
                  {fmt(s.approved_at)}
                </div>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <div className="text-[10px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                Status
              </div>
              <StatusPill status={s.status} />
            </div>
          </div>

          {/* Title Section */}
          <div className="px-4 pb-4 pt-5 sm:px-8 sm:pt-6 print:px-3 print:pt-1.5 print:pb-1">
            <h1 className="text-xl font-bold text-zinc-900 sm:text-2xl dark:text-zinc-100 print:text-base">
              {toDisplay(s.form.name)}
            </h1>
            <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-400 print:text-[9px] print:mt-0">
              Official verification certificate for submission #{s.submission.id}
            </p>
          </div>

          {/* Body */}
          <div className="grid grid-cols-1 gap-x-10 gap-y-8 px-4 py-5 sm:px-6 sm:py-6 lg:grid-cols-[minmax(0,1fr)_360px] print:grid-cols-[minmax(0,1fr)_220px] print:gap-x-3 print:px-2 print:py-2">
            {/* LEFT: Submission Details + Timeline */}
            <div>
              <SectionHeading title="Request Details" description="Submission timeline and requester information." />

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 print:gap-1">
                <div className="space-y-1">
                  <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Submitted</p>
                  <div className={fieldBoxClass}>{fmt(s.submission.created_at)}</div>
                </div>
                <div className="space-y-1">
                  <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Approval Time</p>
                  <div className={fieldBoxClass}>{fmt(s.approved_at)}</div>
                </div>
                <div className="space-y-1 sm:col-span-2">
                  <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Approved By</p>
                  <div className={fieldBoxClass}>{toDisplay(s.approved_by)}</div>
                </div>
              </div>

              <div className="my-6 h-px bg-zinc-200 dark:bg-zinc-800 print:my-2" />

              <SectionHeading title="Submission Details" description="Submitted field values from the request form." />

              <div className="space-y-4 print:space-y-1">
                {(() => {
                  let slotsRendered = false;
                  let rangesRendered = false;

                  return s.fields.map((f: Field, i: number) => {
                    const type = String(f.type || "text").toLowerCase();
                    const parsed = typeof f.value === "string" ? tryParseJSONDeep(f.value) : f.value;
                    const fieldOptions = (f.field_options ?? {}) as Record<string, unknown>;
                    const shownLabel = f.label || prettyLabel(f.name);
                    const hasTableColumnsConfig = Array.isArray(fieldOptions.table_columns) && fieldOptions.table_columns.length > 0;

                    if (type === "section") {
                      const sectionTitle = toDisplay(fieldOptions.section_title ?? shownLabel ?? "Section");
                      const sectionDescription = toDisplay(fieldOptions.section_description);

                      return (
                        <div key={`${f.name || f.label || 'section'}-${i}`} className="py-4 border-t border-zinc-200/60 dark:border-zinc-700/50">
                          <p className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{sectionTitle}</p>
                          {sectionDescription ? <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{sectionDescription}</p> : null}
                        </div>
                      );
                    }

                    if (type === "heading") {
                      const headingText = toDisplay(fieldOptions.heading_content ?? shownLabel ?? "Heading");
                      const headingSize = String(fieldOptions.heading_size || "medium");
                      const headingClass =
                        headingSize === "large"
                          ? "text-xl font-semibold"
                          : headingSize === "small"
                          ? "text-base"
                          : "text-lg font-medium";

                      return (
                        <div key={`${f.name || f.label || 'heading'}-${i}`} className="py-2">
                          <p className={`${headingClass} whitespace-pre-wrap text-zinc-900 dark:text-zinc-100`}>{headingText}</p>
                        </div>
                      );
                    }

                    if (type === "image") {
                      const imageUrl = toDisplay(fieldOptions.image_url ?? fieldOptions.image_path ?? (typeof f.value === "string" ? f.value : ""));
                      const imageAlt = toDisplay(fieldOptions.image_alt ?? shownLabel ?? "Image");

                      return (
                        <div key={`${f.name || f.label || 'image'}-${i}`} className="space-y-2">
                          <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{shownLabel}</p>
                          <div className={fieldBoxClass}>
                            {imageUrl !== "—" ? (
                              <img src={imageUrl} alt={imageAlt} className="max-h-72 w-auto rounded border border-zinc-300 dark:border-zinc-700" />
                            ) : (
                              <span className="text-zinc-500 dark:text-zinc-400">—</span>
                            )}
                          </div>
                        </div>
                      );
                    }

                    if ((f.isFile || type === "file") && f.value) {
                      return null;
                    }

                    const slotData = Array.isArray(parsed) && parsed.length > 0 && !!(parsed[0] as Record<string, unknown>)?.date ? parsed as Array<Record<string, unknown>> : [];
                    if (!slotsRendered && slotData.length > 0 && (looksLikeSlotsLabel(shownLabel) || type.includes("slot") || type.includes("schedule") || type === "date")) {
                      slotsRendered = true;

                      return (
                        <div key={`selected-dates-times-${i}`} className="space-y-2">
                          <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Selected Dates & Times</p>
                          <div className="space-y-2">
                            {slotData.map((slot, idx) => {
                              const rawDate = String(slot.date ?? "");
                              const dateStr = rawDate && !Number.isNaN(Date.parse(rawDate)) ? new Date(rawDate).toLocaleDateString() : rawDate || "—";
                              const start = String(slot.start_time ?? "");
                              const end = String(slot.end_time ?? "");
                              const timeStr = start && end ? ` | ${start} – ${end}` : "";

                              let facilityStr = "";
                              if (slot.facility_name) {
                                // facility_name is frozen in the snapshot payload — no live DB lookup.
                                facilityStr = ` | ${String(slot.facility_name)}`;
                              } else if (slot.facility_id) {
                                facilityStr = ` | Facility ${String(slot.facility_id)}`;
                              }

                              const venueStr = slot.venue ? ` | ${String(slot.venue)}` : "";

                              return (
                                <div key={idx} className={fieldBoxClass}>
                                  {dateStr}{timeStr}{facilityStr}{venueStr}
                                </div>
                              );
                            })}
                          </div>
                        </div>
                      );
                    }

                    const dateRangeData = Array.isArray(parsed)
                      ? parsed.filter((entry): entry is Record<string, unknown> => {
                          if (!entry || typeof entry !== "object") return false;
                          const start = (entry as Record<string, unknown>).start_date ?? (entry as Record<string, unknown>).start;
                          const end = (entry as Record<string, unknown>).end_date ?? (entry as Record<string, unknown>).end;
                          return !!start || !!end;
                        })
                      : [];

                    if (!rangesRendered && dateRangeData.length > 0 && (type.includes("range") || shownLabel.toLowerCase().includes("range"))) {
                      rangesRendered = true;

                      return (
                        <div key={`date-ranges-${i}`} className="space-y-2">
                          <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Date Ranges</p>
                          <div className="space-y-2">
                            {dateRangeData.map((range, idx) => {
                              const rawStart = String(range.start_date ?? range.start ?? "");
                              const rawEnd = String(range.end_date ?? range.end ?? "");
                              const start = rawStart && !Number.isNaN(Date.parse(rawStart)) ? new Date(rawStart).toLocaleDateString() : rawStart || "—";
                              const end = rawEnd && !Number.isNaN(Date.parse(rawEnd)) ? new Date(rawEnd).toLocaleDateString() : rawEnd || "—";

                              return (
                                <div key={idx} className={fieldBoxClass}>
                                  {start} → {end}
                                </div>
                              );
                            })}
                          </div>
                        </div>
                      );
                    }

                    const { isTable, data: tableData, columns } = parseTableData(parsed);
                    if (type === "table" || (isTable && hasTableColumnsConfig)) {
                      return (
                        <div key={`${f.name || f.label || 'table'}-${i}`} className="space-y-2">
                          <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{shownLabel}</p>
                          <div className="overflow-hidden rounded-md border border-zinc-200/60 dark:border-zinc-700/50 dark:bg-zinc-900/40">
                            <div className="overflow-x-auto">
                              <table className="w-full text-left text-[13px]">
                                <thead className="border-b border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                  <tr>
                                    {columns.map((col) => (
                                      <th key={col} className="px-3 py-2 font-semibold text-zinc-700 dark:text-zinc-200">{prettyLabel(col)}</th>
                                    ))}
                                  </tr>
                                </thead>
                                <tbody>
                                  {tableData.map((row, rowIndex) => (
                                    <tr key={rowIndex} className="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                                      {columns.map((col) => (
                                        <td key={`${col}-${rowIndex}`} className="px-3 py-2 text-zinc-900 dark:text-zinc-100">{toDisplay(row[col])}</td>
                                      ))}
                                    </tr>
                                  ))}
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      );
                    }

                    const isLong = typeof f.value === "string" && f.value.length > 100;

                    return (
                      <div key={`${f.name || f.label || 'field'}-${i}`} className="space-y-1">
                        <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{shownLabel}</p>
                        <div className={`${fieldBoxClass} ${isLong ? "min-h-24 whitespace-pre-wrap" : ""}`}>
                          {prettyFieldValue(f)}
                        </div>
                      </div>
                    );
                  }).filter(Boolean);
                })()}
              </div>

              {/* File Attachments Section */}
              {(() => {
                // Find all file fields - check multiple conditions
                const fileFields = s.fields.filter((f: Field) => {
                  // Skip if no value
                  if (!f.value) return false;
                  
                  // Check 1: Explicitly marked as file
                  if (f.isFile) return true;
                  
                  // Check 2: Type is 'file'
                  if (f.type && f.type.toLowerCase() === 'file') return true;
                  
                  // Check 3: Value looks like a file path
                  if (isAttachmentValue(f.value)) return true;
                  
                  return false;
                });
                
                if (fileFields.length === 0) return null;

                return (
                  <>
                    <h2 className="mt-8 mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">
                      Attached Documents
                    </h2>
                    <div className="no-print-buttons space-y-3">
                      {fileFields.map((f: Field, idx: number) => {
                        const filePath = String(f.value);
                        const fileUrl = toPrivateUrl(filePath);
                        const isImg = isImage(filePath) || isImage(f.mime_type || "");
                        const isPdfFile = isPdfCheck(filePath) || isPdfCheck(f.mime_type || "");
                        const canPreview = isImg || isPdfFile;
                        const fileName = filePath.split("/").pop() || f.label;

                        return (
                          <div 
                            key={`attachment-${idx}`}
                            className="rounded-lg border border-zinc-200/60 dark:border-zinc-700/50 p-4 motion-safe:transition-colors hover:bg-zinc-50/50 dark:hover:bg-white/[0.02]"
                          >
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-4">
                              {/* Thumbnail */}
                              <div className="flex-shrink-0">
                                {isImg ? (
                                  <button
                                    onClick={() => setPdfViewer({ 
                                      url: fileUrl,
                                      title: f.label,
                                      mime: f.mime_type || inferMime(filePath)
                                    })}
                                    className="group relative block overflow-hidden rounded-md border border-zinc-200/60 motion-safe:transition-colors hover:border-zinc-300/80 dark:border-zinc-700/60"
                                    aria-label={`Preview ${f.label}`}
                                  >
                                    <img
                                      src={fileUrl}
                                      alt={f.label}
                                      className="h-20 w-28 sm:h-24 sm:w-32 object-cover motion-safe:transition-transform group-hover:scale-105"
                                      loading="lazy"
                                    />
                                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 motion-safe:transition-colors flex items-center justify-center">
                                      <div className="opacity-0 group-hover:opacity-100 transition-opacity bg-white/90 dark:bg-black/90 rounded-full p-2">
                                        <svg className="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                        </svg>
                                      </div>
                                    </div>
                                  </button>
                                ) : (
                                  <div className="flex h-20 w-28 sm:h-24 sm:w-32 items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                                    <FileText className="h-8 w-8 text-zinc-400 dark:text-zinc-500" aria-hidden />
                                  </div>
                                )}
                              </div>

                              {/* Meta & Actions */}
                              <div className="flex-1 min-w-0 space-y-3">
                                {/* File Info */}
                                <div>
                                  <p className="text-sm font-semibold truncate text-zinc-900 dark:text-zinc-100" title={toDisplay(f.label)}>
                                    {toDisplay(f.label)}
                                  </p>
                                  <div className="flex items-center gap-2 mt-1">
                                    <p className="text-xs text-zinc-600 dark:text-zinc-400 truncate">
                                      {toDisplay(fileName)}
                                    </p>
                                  </div>
                                </div>

                                {/* Action Buttons */}
                                <div className="flex flex-wrap gap-2 print:hidden">
                                  {/* Preview Button */}
                                  {canPreview && (
                                    <button
                                      onClick={() => setPdfViewer({ 
                                        url: fileUrl,  
                                        title: f.label, 
                                        mime: f.mime_type || inferMime(filePath)
                                      })}
                                      className="inline-flex items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-transparent px-3 py-2 text-xs font-medium text-zinc-700 motion-safe:transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                      title="Preview"
                                    >
                                      <Eye className="h-3.5 w-3.5" aria-hidden />
                                      Preview
                                    </button>
                                  )}

                                  {/* Open in New Tab */}
                                  <a
                                    href={fileUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-transparent px-3 py-2 text-xs font-medium text-zinc-700 motion-safe:transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                  >
                                    <ExternalLink className="h-3.5 w-3.5" aria-hidden />
                                    Open
                                  </a>

                                  {/* Download Button */}
                                  <a
                                    href={fileUrl}
                                    download
                                    className="inline-flex items-center justify-center gap-1.5 rounded-md border border-zinc-200 bg-transparent px-3 py-2 text-xs font-medium text-zinc-700 motion-safe:transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                  >
                                    <Download className="h-3.5 w-3.5" aria-hidden />
                                    Download
                                  </a>
                                </div>
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </>
                );
              })()}

              {/* Generic Submission Attachments */}
              {attachments.length > 0 && (
                <div className="print-section">
                  <h2 className="mt-8 mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">
                    Additional Attachments
                  </h2>
                  <div className="no-print-buttons space-y-2">
                    {attachments.map((att) => {
                      if (!att.path) return null; // Skip if no path
                      
                      // Use /files/ endpoint for private files (submission attachments)
                      const filePath = toPrivateUrl(att.path);
                      const fileName = att.filename || 'Attachment';
                      const isImg = isImage(fileName);
                      const isPdfFile = isPdfCheck(fileName);
                      const mime = att.mime_type || inferMime(fileName);

                      return (
                        <div
                          key={att.id}
                          className="flex items-center gap-3 py-3 border-b border-zinc-100 last:border-0 dark:border-zinc-800/50 motion-safe:transition-colors hover:bg-zinc-50/50 dark:hover:bg-white/[0.02] print:border-zinc-300"
                        >
                          {/* File type icon */}
                          <div className="flex-shrink-0 flex h-8 w-8 items-center justify-center rounded bg-zinc-100 dark:bg-zinc-800">
                            {isImg ? (
                              <ImageIcon className="h-4 w-4 text-zinc-400 dark:text-zinc-500" aria-hidden />
                            ) : (
                              <FileText className="h-4 w-4 text-zinc-400 dark:text-zinc-500" aria-hidden />
                            )}
                          </div>

                          {/* File info */}
                          <div className="flex-1 min-w-0">
                            <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                              {toDisplay(fileName)}
                            </div>
                            <div className="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                              Uploaded {fmt(att.uploaded_at)}
                            </div>
                          </div>

                          {/* Action buttons */}
                          <div className="flex gap-2 ml-2 print:hidden">
                            {/* Preview (if supported) */}
                            {(isImg || isPdfFile) && (
                              <button
                                onClick={() => setPdfViewer({
                                  url: filePath,
                                  title: fileName,
                                  mime: mime
                                })}
                                className="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 bg-transparent px-3 py-1.5 text-xs font-medium text-zinc-700 motion-safe:transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                title="Preview"
                              >
                                <Eye className="h-3.5 w-3.5" aria-hidden />
                                View
                              </button>
                            )}

                            {/* Download Button */}
                            <a
                              href={filePath}
                              download
                              className="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 bg-transparent px-3 py-1.5 text-xs font-medium text-zinc-700 motion-safe:transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                              title="Download"
                            >
                              <Download className="h-3.5 w-3.5" aria-hidden />
                              Download
                            </a>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}

            </div>

            {/* RIGHT: Verification / Meta */}
            <aside className="border-t border-zinc-200/50 pt-6 lg:border-t-0 lg:border-l lg:border-zinc-200/50 lg:pl-10 lg:pt-0 dark:border-zinc-800/50 dark:lg:border-zinc-700/50 print:border-l print:border-zinc-300 print:pl-2">
              <h2 className="mb-3 text-sm font-semibold tracking-wide text-zinc-700 dark:text-zinc-300">
                Verification
              </h2>

              <div className="flex flex-col items-center gap-2">
                <div className="text-[11px] text-zinc-500 dark:text-zinc-400">Scan to verify</div>
                <div className="qr-box rounded-md border border-zinc-200/60 dark:border-zinc-700/50 p-2">
                  <QRCode value={verifyUrl} size={144} />
                </div>
                <button
                  onClick={copyVerifyUrl}
                  className="print:hidden inline-flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 motion-safe:transition-colors"
                  aria-label="Copy verification link"
                >
                  {linkCopied ? (
                    <>
                      <Check className="h-3.5 w-3.5 text-emerald-500" aria-hidden />
                      Copied!
                    </>
                  ) : (
                    <>
                      <Copy className="h-3.5 w-3.5" aria-hidden />
                      Copy verification link
                    </>
                  )}
                </button>
              </div>

              <dl className="mt-5 grid grid-cols-1 gap-3 text-[12px]">
                <MetaRow label="Certificate ID" value={toDisplay(s.short_code)} />
                <MetaRow label="Form" value={toDisplay(s.form.name)} />
                <MetaRow label="Issued By" value="AUFlow Verification Service" />
              </dl>

              <hr className="my-5 border-zinc-200/60 dark:border-zinc-700/50" />

              <h2 className="mb-3 text-sm font-semibold tracking-wide text-zinc-700 dark:text-zinc-300">
                Approval History
              </h2>
              <CompactTimeline approvals={orderedApprovals} />

              <p className="mt-3 text-[11px] text-zinc-500 dark:text-zinc-400 print:text-[10px]">
                Read-only verification snapshot. Changes to the original request after issuance do not affect this page.
              </p>
            </aside>
          </div>

          {/* Footer */}
          <div className="rounded-b-xl border-t border-zinc-200/50 px-4 py-3 text-center text-[11px] text-zinc-500 sm:px-6 dark:border-zinc-800/50 dark:text-zinc-400 print:px-5">
            AUFlow Verification Service • autogenerated verification copy
          </div>
        </div>
      </div>

      {/* Print rules */}
      <style>{`
        @page { 
          size: A4 portrait; 
          margin: 5mm 8mm;
        }
        @media print {
          html, body {
            width: 210mm !important;
            background: #ffffff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          #print-page { 
            width: 100% !important; 
            max-width: none !important; 
            margin: 0 !important; 
            padding: 0 !important;
          }
          #certificate { 
            border-radius: 0 !important; 
            box-shadow: none !important; 
            background: #ffffff !important;
            border: none !important;
            overflow: visible !important;
          }

          /* ── Header bar ── */
          #certificate > div:first-child {
            padding: 4px 10px !important;
          }
          #certificate > div:first-child img {
            height: 28px !important;
            width: 28px !important;
          }
          #certificate > div:first-child .text-base {
            font-size: 11px !important;
            line-height: 1.2 !important;
          }
          #certificate > div:first-child .text-xs {
            font-size: 9px !important;
          }

          /* ── Info bar ── */
          #certificate > div:nth-child(2) {
            padding: 3px 10px !important;
          }

          /* ── Title section ── */
          #certificate h1 {
            font-size: 13px !important;
            margin-bottom: 1px !important;
            line-height: 1.3 !important;
          }
          #certificate h1 + p {
            font-size: 9px !important;
            margin-top: 0 !important;
          }
          #certificate .pt-6 { padding-top: 5px !important; }
          #certificate .pb-4 { padding-bottom: 3px !important; }

          /* ── Body grid ── */
          #certificate .px-6, #certificate .px-8 { 
            padding-left: 8px !important; 
            padding-right: 8px !important; 
          }
          #certificate .py-6 { 
            padding-top: 5px !important; 
            padding-bottom: 5px !important; 
          }
          #certificate .gap-x-10 { column-gap: 12px !important; }
          #certificate .gap-y-8  { row-gap: 5px !important; }

          /* Sidebar narrower in print */
          #certificate .lg\\:grid-cols-\\[minmax\\(0\\,1fr\\)_360px\\] {
            grid-template-columns: minmax(0,1fr) 240px !important;
          }

          /* ── Section headings ── */
          #certificate h2 {
            font-size: 9.5px !important;
            line-height: 1.2 !important;
            margin-bottom: 2px !important;
            break-after: avoid;
            page-break-after: avoid;
          }
          #certificate h2 + p,
          #certificate .mb-4 + .space-y-4,
          #certificate p.text-sm.text-zinc-600 {
            font-size: 9px !important;
            margin-bottom: 3px !important;
          }

          /* ── Spacing resets ── */
          #certificate .my-6 { margin-top: 5px !important; margin-bottom: 5px !important; }
          #certificate .my-5 { margin-top: 4px !important; margin-bottom: 4px !important; }
          #certificate .mb-4 { margin-bottom: 4px !important; }
          #certificate .mb-3 { margin-bottom: 3px !important; }
          #certificate .mb-2 { margin-bottom: 2px !important; }
          #certificate .mt-8 { margin-top: 6px !important; }
          #certificate .mt-5 { margin-top: 4px !important; }
          #certificate .gap-4 { gap: 4px !important; }
          #certificate .gap-3 { gap: 3px !important; }
          #certificate .gap-2 { gap: 2px !important; }

          /* ── Field boxes ── */
          #certificate .space-y-4 > * + * { margin-top: 4px !important; }
          #certificate .space-y-3 > * + * { margin-top: 3px !important; }
          #certificate .space-y-2 > * + * { margin-top: 2px !important; }
          #certificate .space-y-1 > * + * { margin-top: 1px !important; }
          
          /* Compact field box padding */
          #certificate .rounded-md.border.border-zinc-300 {
            padding: 2px 6px !important;
            font-size: 10px !important;
          }

          /* ── Text sizes ── */
          #certificate .text-sm  { font-size: 10px !important; }
          #certificate .text-xs  { font-size: 9px !important; }
          #certificate .text-\\[13px\\] { font-size: 10px !important; }
          #certificate .text-\\[11px\\] { font-size: 9px !important; }
          #certificate .text-\\[10px\\] { font-size: 8.5px !important; }
          #certificate .text-\\[12px\\] { font-size: 9.5px !important; }
          #certificate ol li p.text-sm { font-size: 9.5px !important; }
          #certificate ol li .text-xs  { font-size: 8.5px !important; }
          #certificate ol li { padding-bottom: 4px !important; }

          /* ── QR code ── */
          .qr-box { 
            padding: 3px !important; 
            border: 1px solid #d4d4d8 !important; 
            break-inside: avoid;
            page-break-inside: avoid;
          }
          .qr-box svg {
            width: 88px !important;
            height: 88px !important;
          }
          #certificate .text-\\[11px\\].text-zinc-600 { font-size: 8.5px !important; }

          /* ── Sidebar aside ── */
          #certificate aside {
            border-left-width: 1px !important;
            padding-left: 8px !important;
          }

          /* ── Attachments ── */
          #certificate .rounded-lg.border.border-zinc-300\\/60 {
            padding: 4px !important;
          }
          #certificate .h-20 { height: 48px !important; }
          #certificate .w-28 { width: 60px !important; }

          /* ── Footer ── */
          #certificate .border-t.border-zinc-300.px-6.py-3 {
            padding: 2px 8px !important;
            font-size: 8px !important;
          }

          /* ── Keep things together ── */
          dl { break-inside: avoid; page-break-inside: avoid; }
          .qr-box { break-inside: avoid; page-break-inside: avoid; }
          .no-break { break-inside: avoid; page-break-inside: avoid; }

          /* ── Cosmetic resets ── */
          .shadow, .shadow-sm, .shadow-md, .shadow-lg { box-shadow: none !important; }
          .bg-gradient-to-r { background-image: none !important; }
          .border-dashed { border-style: solid !important; }
          .aspect-video { aspect-ratio: 16/9 !important; max-height: 100px !important; }
        }
      `}</style>

      {/* File Viewer Modal */}
      <FileViewerDialog
        open={!!pdfViewer}
        onOpenChange={(o) => { if (!o) setPdfViewer(null); }}
        url={pdfViewer?.url || ""}
        title={pdfViewer?.title || "Preview"}
        mime={pdfViewer?.mime}
      />
    </>
  );
}

/* ---------- Small primitives ---------- */
function MetaRow({ label, value }: { label: string; value: string }) {
  return (
    <>
      <dt className="text-[10px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{label}</dt>
      <dd className="text-[12px] text-zinc-900 dark:text-zinc-100">{value || "—"}</dd>
    </>
  );
}