import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Pencil } from 'lucide-react';
import type { User } from './types';

function formatDate(v?: string | null, includeTime = false): string {
  if (!v) return '—';
  const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(v.trim());
  const d = isDateOnly ? new Date(`${v}T00:00:00`) : new Date(v);
  if (isNaN(d.getTime())) return v;
  if (includeTime) {
    return new Intl.DateTimeFormat('en', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(d);
  }
  return new Intl.DateTimeFormat('en', { year: 'numeric', month: 'long', day: 'numeric' }).format(d);
}

export default function ViewUser() {
  const { user } = usePage<{ user: User }>().props;

  const fullName = (user.profile?.first_name || user.profile?.last_name)
    ? `${user.profile?.first_name ?? ''} ${user.profile?.last_name ?? ''}`.trim()
    : user.name;

  const profilePictureUrl = user.profile?.profile_picture_url
    ?? (user.profile?.profile_picture
      ? (user.profile.profile_picture.startsWith('http') || user.profile.profile_picture.startsWith('/'))
        ? user.profile.profile_picture
        : `/storage/${user.profile.profile_picture}`
      : undefined);

  const initials = (
    (user.profile?.first_name?.[0] ?? '') + (user.profile?.last_name?.[0] ?? '')
  ).toUpperCase() || user.name?.[0]?.toUpperCase() || '?';

  return (
    <AppLayout title="View User">
      <Head title="View User" />

      <div className="mx-auto w-full max-w-2xl px-4 py-5 sm:px-6 sm:py-6 space-y-4">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Avatar className="h-12 w-12 shrink-0">
            <AvatarImage src={profilePictureUrl} alt={fullName} className="object-cover" />
            <AvatarFallback>{initials}</AvatarFallback>
          </Avatar>
          <div className="flex-1 min-w-0">
            <h1 className="truncate text-xl font-semibold text-foreground">{fullName}</h1>
            <p className="truncate text-sm text-muted-foreground">{user.email}</p>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link href={route('user-management.users.edit', user.account_id)}>
              <Pencil className="mr-1.5 h-4 w-4" /> Edit
            </Link>
          </Button>
        </div>

        <div className="rounded-lg border border-border/50 divide-y divide-border/50">
          <div className="flex items-center justify-between px-5 py-3">
            <span className="text-sm text-muted-foreground">Username</span>
            <span className="text-sm font-medium text-foreground">{user.name}</span>
          </div>
          <div className="flex items-center justify-between px-5 py-3">
            <span className="text-sm text-muted-foreground">Status</span>
            <span className="text-sm font-medium text-foreground">{user.status?.status_name ?? '—'}</span>
          </div>
          <div className="flex items-start justify-between gap-4 px-5 py-3">
            <span className="shrink-0 text-sm text-muted-foreground">Roles</span>
            <div className="flex flex-wrap justify-end gap-1.5">
              {user.roles?.length
                ? user.roles.map((r) => (
                    <Badge key={r.role_id} variant="secondary" className="text-xs">
                      {r.role_name}
                    </Badge>
                  ))
                : <span className="text-sm font-medium text-foreground">—</span>}
            </div>
          </div>
          <div className="flex items-center justify-between px-5 py-3">
            <span className="text-sm text-muted-foreground">Created</span>
            <span className="text-sm font-medium text-foreground">{formatDate(user.created_at, true)}</span>
          </div>
          {user.profile?.employee_id && (
            <div className="flex items-center justify-between px-5 py-3">
              <span className="text-sm text-muted-foreground">Employee ID</span>
              <span className="text-sm font-medium text-foreground">{user.profile.employee_id}</span>
            </div>
          )}
          {user.profile?.student_id && (
            <div className="flex items-center justify-between px-5 py-3">
              <span className="text-sm text-muted-foreground">Student ID</span>
              <span className="text-sm font-medium text-foreground">{user.profile.student_id}</span>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
