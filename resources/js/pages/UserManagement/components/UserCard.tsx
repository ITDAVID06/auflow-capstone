import * as React from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { Mail, MoreHorizontal, Pencil, Archive } from 'lucide-react';
import type { User } from '../types';

/* ---- helpers ---- */

function statusBadgeClass(statusName: string): string {
  if (statusName === 'active') return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
  if (statusName === 'inactive') return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
  return 'bg-muted text-muted-foreground';
}

function initials(u: User): string {
  const f = u.profile?.first_name?.[0] || '';
  const l = u.profile?.last_name?.[0] || '';
  return (f + l).toUpperCase() || u.name?.[0]?.toUpperCase() || '?';
}

function getProfilePictureUrl(user: User): string | undefined {
  return (
    user.profile?.profile_picture_url ??
    (user.profile?.profile_picture
      ? user.profile.profile_picture.startsWith('http') ||
        user.profile.profile_picture.startsWith('/')
        ? user.profile.profile_picture
        : `/storage/${user.profile.profile_picture}`
      : undefined)
  );
}

/* ---- types ---- */

type Props = {
  user: User;
  onView: (u: User) => void;
  onEdit: (u: User) => void;
  onArchive: (u: User) => void;
};

/* ---- component ---- */

export default function UserCard({ user, onView, onEdit, onArchive }: Props) {
  const statusName = (user.status?.status_name || 'unknown').toLowerCase();
  const isArchived = statusName === 'archive';
  const primaryRole =
    Array.isArray(user.roles) && user.roles.length ? user.roles[0].role_name : null;
  const fullName = user.profile
    ? `${user.profile.first_name ?? ''} ${user.profile.last_name ?? ''}`.trim() || user.name
    : user.name;

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={() => onView(user)}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onView(user);
        }
      }}
      aria-label={`View ${fullName}`}
      className="group relative flex flex-col rounded-xl border border-border/60 bg-card cursor-pointer motion-safe:transition-colors hover:bg-accent/20 hover:border-border focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
    >
      {/* Action buttons — fade in on hover / focus-within */}
      <div
        className="absolute right-2.5 top-2.5 z-10 flex items-center gap-0.5 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 motion-safe:transition-opacity"
        onClick={(e) => e.stopPropagation()}
      >
        <Tooltip>
          <TooltipTrigger asChild>
            <button
              type="button"
              onClick={() => onEdit(user)}
              aria-label={`Edit ${fullName}`}
              className="inline-flex h-7 w-7 items-center justify-center rounded-md bg-background/90 text-muted-foreground backdrop-blur-sm motion-safe:transition-colors hover:bg-accent hover:text-foreground touch-manipulation"
            >
              <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
            </button>
          </TooltipTrigger>
          <TooltipContent>Edit</TooltipContent>
        </Tooltip>

        {!isArchived && (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className="inline-flex h-7 w-7 items-center justify-center rounded-md bg-background/90 text-muted-foreground backdrop-blur-sm motion-safe:transition-colors hover:bg-accent hover:text-foreground touch-manipulation"
                aria-label="More actions"
              >
                <MoreHorizontal className="h-3.5 w-3.5" aria-hidden="true" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
              <DropdownMenuItem variant="destructive" onClick={() => onArchive(user)}>
                <Archive className="mr-2 h-4 w-4" aria-hidden="true" />
                Archive
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </div>

      {/* Main body — centered layout */}
      <div className="flex flex-col items-center px-4 pt-7 pb-4 text-center">
        <Avatar className="h-12 w-12 ring-2 ring-border/60">
          <AvatarImage src={getProfilePictureUrl(user)} alt="" />
          <AvatarFallback className="text-sm font-semibold">{initials(user)}</AvatarFallback>
        </Avatar>

        <div className="mt-3 min-w-0 w-full space-y-0.5">
          <p className="text-sm font-semibold text-foreground truncate" title={fullName}>
            {fullName}
          </p>
          <p className="text-xs text-muted-foreground truncate">@{user.name}</p>
        </div>

        {/* Status + role badges */}
        <div className="mt-2.5 flex flex-wrap justify-center gap-1">
          <span
            className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium capitalize ${statusBadgeClass(statusName)}`}
          >
            {statusName}
          </span>
          {primaryRole && (
            <span className="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
              {primaryRole}
            </span>
          )}
        </div>
      </div>

      {/* Footer — email */}
      <div className="flex items-center gap-2 border-t border-border/50 px-4 py-2.5">
        <Mail className="h-3.5 w-3.5 shrink-0 text-muted-foreground/60" aria-hidden="true" />
        <span
          className="min-w-0 truncate text-xs text-muted-foreground"
          title={user.email || undefined}
        >
          {user.email || '—'}
        </span>
      </div>
    </div>
  );
}