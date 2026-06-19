import * as React from 'react';
import {
  Dialog,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Mail, Phone, Shield, Calendar, MapPin, Hash, Pencil } from 'lucide-react';
import type { User } from '../types';

interface Props {
  open: boolean;
  user: User | null;
  onClose: () => void;
  onEditClick?: (user: User) => void;
}

/* ---------- helpers ---------- */

function initials(u?: User | null) {
  if (!u) return '';
  const fn = u.profile?.first_name || '';
  const ln = u.profile?.last_name || '';
  const base = `${fn} ${ln}`.trim() || u.name || '';
  return base
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((s) => s[0]?.toUpperCase())
    .join('');
}

const statusClass: Record<string, string> = {
  active: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
  inactive: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
  suspended: 'bg-red-500/10 text-red-700 dark:text-red-400',
  archive: 'bg-muted text-muted-foreground',
};

const dash = (v?: string | null) =>
  v && String(v).trim() !== '' ? String(v) : '—';

function formatDate(v?: string | null, includeTime = false): string {
  if (!v) return '—';
  const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(v.trim());
  const d = isDateOnly ? new Date(`${v}T00:00:00`) : new Date(v);
  if (isNaN(d.getTime())) return v;
  if (includeTime) {
    return new Intl.DateTimeFormat('en', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(d);
  }
  return new Intl.DateTimeFormat('en', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(d);
}

/* ---------- sub-components ---------- */

function SectionTitle({ children }: { children: React.ReactNode }) {
  return (
    <h4 className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
      {children}
    </h4>
  );
}

/** Horizontal label + value row for Account / Access sections */
function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start gap-3 px-3 py-2">
      <span className="w-16 shrink-0 pt-px text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
        {label}
      </span>
      <span className="min-w-0 flex-1 break-words text-sm text-foreground">{value}</span>
    </div>
  );
}

/** Cell for the 2-column profile grid */
function ProfileCell({
  label,
  value,
  className = '',
}: {
  label: string;
  value: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={`flex flex-col px-3 py-2.5 ${className}`}>
      <span className="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
        {label}
      </span>
      <span className="truncate text-sm text-foreground">{value ?? '—'}</span>
    </div>
  );
}

/* ---------- dialog ---------- */

export default function ViewUserDialog({ open, user, onClose, onEditClick }: Props) {
  const fullName = user
    ? user.profile?.first_name || user.profile?.last_name
      ? `${user.profile?.first_name ?? ''} ${user.profile?.last_name ?? ''}`.trim()
      : user.name
    : '';

  const s = (user?.status?.status_name || 'unknown').toLowerCase();
  const pill = statusClass[s] ?? 'bg-muted text-muted-foreground';

  const roles = user?.roles ?? [];

  const profilePictureUrl =
    user?.profile?.profile_picture_url ??
    (user?.profile?.profile_picture
      ? user.profile.profile_picture.startsWith('http') ||
        user.profile.profile_picture.startsWith('/')
        ? user.profile.profile_picture
        : `/storage/${user.profile.profile_picture}`
      : undefined);

  return (
    <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
      <DialogContent hideClose className="max-w-2xl w-full p-0 gap-0 max-h-[90vh] overflow-hidden flex flex-col">
        <DialogTitle className="sr-only">User Details</DialogTitle>
        <DialogDescription className="sr-only">
          Read-only view of the selected user's account information.
        </DialogDescription>

        {user && (
          <>
            {/* ── Hero header ── */}
            <div className="flex items-start gap-4 shrink-0 border-b border-border/60 px-6 py-5">
              <Avatar className="h-14 w-14 shrink-0 ring-2 ring-border/60">
                <AvatarImage src={profilePictureUrl} alt="" />
                <AvatarFallback className="text-base font-semibold">
                  {initials(user)}
                </AvatarFallback>
              </Avatar>

              <div className="flex-1 min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h2
                    className="text-base font-semibold text-foreground truncate"
                    title={fullName}
                  >
                    {fullName}
                  </h2>
                  <span
                    className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium capitalize ${pill}`}
                  >
                    {user.status?.status_name ?? 'Unknown'}
                  </span>
                </div>

                <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                  <span className="text-sm text-muted-foreground">@{user.name}</span>
                  {user.email && (
                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                      <Mail className="h-3 w-3" aria-hidden="true" />
                      {user.email}
                    </span>
                  )}
                </div>

                {roles.length > 0 && (
                  <div className="mt-1.5 flex flex-wrap gap-1">
                    {roles.map((r) => (
                      <Badge
                        key={`${user.account_id}-hero-${r.role_id}`}
                        variant="secondary"
                        className="px-1.5 py-0 text-[11px]"
                      >
                        {r.role_name}
                      </Badge>
                    ))}
                  </div>
                )}
              </div>

              {onEditClick && (
                <button
                  type="button"
                  className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-border/60 px-3 py-1.5 text-sm font-medium text-foreground motion-safe:transition-colors hover:bg-accent touch-manipulation"
                  onClick={() => onEditClick(user)}
                >
                  <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                  Edit
                </button>
              )}
            </div>

            {/* ── Scrollable content ── */}
            <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">

              {/* Account + Access side-by-side */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {/* Account */}
                <section>
                  <SectionTitle>Account</SectionTitle>
                  <div className="overflow-hidden rounded-lg border border-border/60 bg-card divide-y divide-border/50">
                    <InfoRow label="Username" value={dash(user.name)} />
                    <InfoRow label="Email" value={dash(user.email)} />
                    <InfoRow label="Joined" value={formatDate(user.created_at, true)} />
                  </div>
                </section>

                {/* Access */}
                <section>
                  <SectionTitle>Access</SectionTitle>
                  <div className="overflow-hidden rounded-lg border border-border/60 bg-card">
                    <div className="flex items-center justify-between px-3 py-2 border-b border-border/50">
                      <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Status
                      </span>
                      <span
                        className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium capitalize ${pill}`}
                      >
                        {user.status?.status_name ?? 'Unknown'}
                      </span>
                    </div>
                    <div className="px-3 py-2.5">
                      <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Roles
                      </p>
                      {roles.length ? (
                        <div className="flex flex-wrap gap-1">
                          {roles.map((r) => (
                            <Badge
                              key={`${user.account_id}-${r.role_id}`}
                              variant="secondary"
                              className="text-[11px]"
                            >
                              {r.role_name}
                            </Badge>
                          ))}
                        </div>
                      ) : (
                        <p className="text-sm text-muted-foreground">No roles assigned</p>
                      )}
                    </div>
                  </div>
                </section>
              </div>

              {/* Profile — 2-column grid */}
              <section>
                <SectionTitle>Profile</SectionTitle>
                <div className="overflow-hidden rounded-lg border border-border/60 bg-card">
                  <div className="grid grid-cols-1 sm:grid-cols-2">
                    <ProfileCell
                      label="First Name"
                      value={dash(user.profile?.first_name)}
                      className="sm:border-r border-border/50"
                    />
                    <ProfileCell
                      label="Last Name"
                      value={dash(user.profile?.last_name)}
                    />
                    <ProfileCell
                      label="Middle Name"
                      value={dash(user.profile?.middle_name)}
                      className="border-t border-border/50 sm:border-r"
                    />
                    <ProfileCell
                      label="Date of Birth"
                      value={
                        <span className="flex items-center gap-1.5">
                          <Calendar className="h-3.5 w-3.5 shrink-0 text-muted-foreground/60" aria-hidden="true" />
                          {formatDate(user.profile?.date_of_birth)}
                        </span>
                      }
                      className="border-t border-border/50"
                    />
                    <ProfileCell
                      label="Gender"
                      value={dash(user.profile?.gender)}
                      className="border-t border-border/50 sm:border-r"
                    />
                    <ProfileCell
                      label="Phone"
                      value={
                        user.profile?.phone ? (
                          <span className="flex items-center gap-1.5">
                            <Phone className="h-3.5 w-3.5 shrink-0 text-muted-foreground/60" aria-hidden="true" />
                            {user.profile.phone}
                          </span>
                        ) : (
                          '—'
                        )
                      }
                      className="border-t border-border/50"
                    />
                    <ProfileCell
                      label="Address"
                      value={
                        user.profile?.address ? (
                          <span className="flex items-center gap-1.5">
                            <MapPin className="h-3.5 w-3.5 shrink-0 text-muted-foreground/60" aria-hidden="true" />
                            {user.profile.address}
                          </span>
                        ) : (
                          '—'
                        )
                      }
                      className="border-t border-border/50 sm:col-span-2"
                    />
                    <ProfileCell
                      label="Student ID"
                      value={
                        user.profile?.student_id ? (
                          <span className="flex items-center gap-1.5">
                            <Hash className="h-3.5 w-3.5 shrink-0 text-muted-foreground/60" aria-hidden="true" />
                            {user.profile.student_id}
                          </span>
                        ) : (
                          '—'
                        )
                      }
                      className="border-t border-border/50 sm:border-r"
                    />
                    <ProfileCell
                      label="Employee ID"
                      value={
                        user.profile?.employee_id ? (
                          <span className="flex items-center gap-1.5">
                            <Shield className="h-3.5 w-3.5 shrink-0 text-muted-foreground/60" aria-hidden="true" />
                            {user.profile.employee_id}
                          </span>
                        ) : (
                          '—'
                        )
                      }
                      className="border-t border-border/50"
                    />
                  </div>
                </div>
              </section>
            </div>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}
