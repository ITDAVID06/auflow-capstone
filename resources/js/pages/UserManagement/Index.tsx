import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Archive, Eye, Loader2, Pencil, MoreHorizontal, Users, UserCheck, UserMinus, ChevronUp, ChevronDown, ChevronsUpDown, Copy, Check } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import ViewUserDialog from './components/ViewUserDialog';

import AddUserDialog from './components/AddUserDialog';
import EditUserDialog from './components/EditUserDialog';

import UserToolbar from './components/UserToolbar';
import UserCard from './components/UserCard';
import Pagination from '@/components/shared/Pagination';

import type { User, Role, UserStatusOption } from './types';
import { toast } from 'sonner';

const LIST_PER_PAGE = 7;
const GRID_PER_PAGE = 9;

const METRIC_CONFIG = [
  { key: 'total' as const, label: 'Total Users', Icon: Users, valueClass: 'text-foreground', iconClass: 'text-muted-foreground' },
  { key: 'active' as const, label: 'Active', Icon: UserCheck, valueClass: 'text-emerald-700 dark:text-emerald-400', iconClass: 'text-emerald-500 dark:text-emerald-400' },
  { key: 'inactive' as const, label: 'Inactive', Icon: UserMinus, valueClass: 'text-amber-700 dark:text-amber-400', iconClass: 'text-amber-500 dark:text-amber-400' },
  { key: 'archived' as const, label: 'Archived', Icon: Archive, valueClass: 'text-rose-700 dark:text-rose-400', iconClass: 'text-rose-500 dark:text-rose-400' },
] as const;

function metricCellBorder(i: number): string {
  if (i === 1) return 'border-l border-border/60';
  if (i === 2) return 'border-t border-border/60 sm:border-t-0 sm:border-l sm:border-border/60';
  if (i === 3) return 'border-l border-t border-border/60 sm:border-t-0';
  return '';
}

type UserSortColumn = 'name' | 'email' | 'status' | 'created_at';

function SortButton({
  label,
  column,
  currentSort,
  currentDirection,
  onSort,
}: {
  label: string;
  column: UserSortColumn;
  currentSort?: string | null;
  currentDirection?: 'asc' | 'desc' | null;
  onSort: (col: UserSortColumn) => void;
}) {
  const isActive = currentSort === column;
  const Icon = isActive
    ? currentDirection === 'asc'
      ? ChevronUp
      : ChevronDown
    : ChevronsUpDown;
  return (
    <button
      type="button"
      onClick={() => onSort(column)}
      className="inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground motion-safe:transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded touch-manipulation"
    >
      {label}
      <Icon
        className={`h-3 w-3 ${isActive ? 'text-foreground' : 'text-muted-foreground/30'}`}
        aria-hidden="true"
      />
    </button>
  );
}

export default function Index() {
  const props = usePage().props as unknown as {
    users: User[];
    pagination?: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
    filters?: {
      search?: string | null;
      status?: 'all' | 'active' | 'inactive' | 'archive' | null;
      per_page?: number | null;      sort?: string | null;
      direction?: 'asc' | 'desc' | null;    };
    metrics?: {
      total: number;
      active: number;
      inactive: number;
      archived: number;
    };
    roles: Role[];
    statuses: UserStatusOption[];
  };

  const {
    users = [],
    pagination,
    filters,
    metrics,
    roles = [],
    statuses = [],
  } = props;

  const safeUsers: User[] = Array.isArray(users) ? users : [];

  // UI state
  const [showAddUser, setShowAddUser] = useState(false);
  const [editUser, setEditUser] = useState<User | null>(null);

  // Toolbar state
  const [search, setSearch] = useState(filters?.search ?? '');
  const [statusFilter, setStatusFilter] =
    useState<'all' | 'active' | 'inactive' | 'archive'>(filters?.status ?? 'all');
  const [viewMode, setViewMode] = useState<'list' | 'grid'>(
    filters?.per_page === GRID_PER_PAGE ? 'grid' : 'list'
  );
  const [loading, setLoading] = useState(false);
  const [sortColumn, setSortColumn] = useState<UserSortColumn | null>(
    (filters?.sort as UserSortColumn) ?? null
  );
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>(
    filters?.direction ?? 'asc'
  );

  const perPage = viewMode === 'list' ? LIST_PER_PAGE : GRID_PER_PAGE;

  const fetchUsers = (next: Partial<{ search: string; status: 'all' | 'active' | 'inactive' | 'archive'; page: number; sort: UserSortColumn | null; direction: 'asc' | 'desc' }>) => {
    const nextSearch = next.search ?? search;
    const nextStatus = next.status ?? statusFilter;
    const nextPage = next.page ?? pagination?.current_page ?? 1;
    const nextSort = 'sort' in next ? next.sort : sortColumn;
    const nextDirection = next.direction ?? sortDirection;

    setLoading(true);
    router.get(
      route('user-management.users.index'),
      {
        search: nextSearch || undefined,
        status: nextStatus === 'all' ? undefined : nextStatus,
        page: nextPage,
        per_page: perPage,
        sort: nextSort ?? undefined,
        direction: nextSort ? nextDirection : undefined,
      },
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['users', 'pagination', 'filters', 'metrics'],
        onFinish: () => setLoading(false),
      }
    );
  };

  const handleSort = (column: UserSortColumn) => {
    const newDirection: 'asc' | 'desc' =
      sortColumn === column && sortDirection === 'asc' ? 'desc' : 'asc';
    setSortColumn(column);
    setSortDirection(newDirection);
    fetchUsers({ sort: column, direction: newDirection, page: 1 });
  };

  // Handle status filter change
  const handleStatusFilterChange = (newStatus: 'all' | 'active' | 'inactive' | 'archive') => {
    setStatusFilter(newStatus);
    fetchUsers({ status: newStatus, page: 1 });
  };

  const [viewUser, setViewUser] = useState<User | null>(null);
  const handleView = (u: User) => setViewUser(u);

  const [copiedEmailId, setCopiedEmailId] = useState<number | null>(null);
  const handleCopyEmail = (e: React.MouseEvent, u: User) => {
    e.stopPropagation();
    if (!u.email) return;
    navigator.clipboard.writeText(u.email).then(() => {
      setCopiedEmailId(u.account_id);
      setTimeout(() => setCopiedEmailId(null), 2000);
    });
  };

  const [archiveTarget, setArchiveTarget] = useState<number | null>(null);

  const handleArchive = (accountId: number) => {
    setArchiveTarget(accountId);
  };

  const confirmArchive = () => {
    if (archiveTarget === null) return;
    router.delete(route('user-management.users.destroy', archiveTarget), {
      onSuccess: () => {
        toast.success('User archived successfully');
        router.reload({ only: ['users', 'metrics'] });
      },
      onError: () => toast.error('Failed to archive user'),
      preserveScroll: true,
    });
    setArchiveTarget(null);
  };




  return (
    <AppLayout title="User Management" subtitle="Manage system users and their roles">
      <Head title="User Management" />

      <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
        {/* Metric belt */}
        <dl
          className="grid grid-cols-2 overflow-hidden rounded-lg border border-border/60 bg-card sm:grid-cols-4"
          aria-label="User metrics"
          data-tour="users-metrics"
        >
          {METRIC_CONFIG.map((m, i) => {
            const Icon = m.Icon;
            return (
              <div key={m.key} className={`flex flex-col gap-1.5 px-5 py-5 ${metricCellBorder(i)}`}>
                <dt className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                  <Icon className={`h-3.5 w-3.5 ${m.iconClass}`} aria-hidden="true" />
                  {m.label}
                </dt>
                <dd className={`text-3xl font-semibold tabular-nums leading-none ${m.valueClass}`}>
                  {(metrics?.[m.key] ?? 0).toLocaleString()}
                </dd>
              </div>
            );
          })}
        </dl>


        {/* Toolbar */}
        <UserToolbar
          searchValue={search}
          onSearchChange={(v) => {
            setSearch(v);
            fetchUsers({ search: v, page: 1 });
          }}
          statusFilter={statusFilter}
          onStatusChange={handleStatusFilterChange}
          onAddUser={() => setShowAddUser(true)}
          viewMode={viewMode}
          onViewModeChange={(mode) => {
            setViewMode(mode);
            fetchUsers({ page: 1 });
          }}
        />

        {/* List view */}
        <div className="relative">
          {loading && (
            <div className="absolute inset-0 z-10 flex items-center justify-center bg-background/60">
              <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          )}
          {viewMode === 'list' && (
          <div className="overflow-hidden rounded-lg border border-border/60 bg-card" data-tour="users-card">
            {/* Column header */}
            <div className="hidden sm:grid grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.5fr)_88px] gap-4 border-b border-border/60 bg-muted/40 px-5 py-2.5">
              <SortButton label="User" column="name" currentSort={sortColumn} currentDirection={sortDirection} onSort={handleSort} />
              <SortButton label="Status / Role" column="status" currentSort={sortColumn} currentDirection={sortDirection} onSort={handleSort} />
              <SortButton label="Email" column="email" currentSort={sortColumn} currentDirection={sortDirection} onSort={handleSort} />
              <span className="sr-only">Actions</span>
            </div>

            {safeUsers.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-16 text-center">
                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-muted/60">
                  <Users className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
                </div>
                <p className="text-sm font-semibold text-foreground">No users found</p>
                <p className="mt-1 text-xs text-muted-foreground">Try adjusting your search or filter criteria.</p>
              </div>
            ) : (
              <div className="divide-y divide-border/50">
                {safeUsers.map((u) => {
                  const statusName = (u.status?.status_name || 'unknown').toLowerCase();
                  const isArchived = statusName === 'archive';
                  const primaryRole = Array.isArray(u.roles) && u.roles.length ? u.roles[0].role_name : null;
                  const fullName = u.profile
                    ? `${u.profile.first_name ?? ''} ${u.profile.last_name ?? ''}`.trim() || u.name
                    : u.name;
                  const profilePictureUrl = u.profile?.profile_picture_url
                    ?? (u.profile?.profile_picture
                      ? (u.profile.profile_picture.startsWith('http') || u.profile.profile_picture.startsWith('/'))
                        ? u.profile.profile_picture
                        : `/storage/${u.profile.profile_picture}`
                      : undefined);
                  const statusCls = statusName === 'active'
                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                    : statusName === 'inactive'
                    ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                    : 'bg-muted text-muted-foreground';
                  const initials = (
                    (u.profile?.first_name?.[0] || '') +
                    (u.profile?.last_name?.[0] || '')
                  ).toUpperCase() || u.name?.[0]?.toUpperCase() || '?';

                  return (
                    <div
                      key={u.account_id}
                      role="button"
                      tabIndex={0}
                      onClick={() => handleView(u)}
                      onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleView(u); } }}
                      className="group grid cursor-pointer grid-cols-1 gap-y-1 px-5 py-3 motion-safe:transition-colors hover:bg-accent/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.5fr)_88px] sm:items-center sm:gap-4 sm:gap-y-0"
                    >
                      {/* User */}
                      <div className="flex min-w-0 items-center gap-2.5">
                        <Avatar className="h-7 w-7 shrink-0 ring-1 ring-border/60">
                          <AvatarImage src={profilePictureUrl} alt={fullName} className="object-cover" />
                          <AvatarFallback className="text-xs">{initials}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                          <p className="line-clamp-1 text-sm font-semibold text-foreground">{fullName}</p>
                          <p className="truncate text-xs text-muted-foreground">@{u.name}</p>
                        </div>
                      </div>

                      {/* Status + Role */}
                      <div className="flex flex-wrap items-center gap-1">
                        <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium capitalize ${statusCls}`}>
                          {statusName}
                        </span>
                        {primaryRole && (
                          <span className="inline-flex items-center rounded-md bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">
                            {primaryRole}
                          </span>
                        )}
                      </div>

                      {/* Email */}
                      <div className="hidden min-w-0 sm:flex sm:items-center sm:gap-1 group/email">
                        <span className="truncate text-xs text-muted-foreground">{u.email || '—'}</span>
                        {u.email && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <button
                                type="button"
                                onClick={(e) => handleCopyEmail(e, u)}
                                className="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-muted-foreground opacity-0 group-hover/email:opacity-100 motion-safe:transition-opacity hover:text-foreground"
                                aria-label="Copy email"
                              >
                                {copiedEmailId === u.account_id
                                  ? <Check className="h-3 w-3 text-emerald-500" />
                                  : <Copy className="h-3 w-3" />}
                              </button>
                            </TooltipTrigger>
                            <TooltipContent>{copiedEmailId === u.account_id ? 'Copied!' : 'Copy email'}</TooltipContent>
                          </Tooltip>
                        )}
                      </div>

                      {/* Actions */}
                      <div className="flex items-center justify-end gap-0.5" onClick={(e) => e.stopPropagation()}>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <button
                              type="button"
                              onClick={() => handleView(u)}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground motion-safe:transition-colors hover:bg-accent hover:text-foreground"
                              aria-label="View user"
                            >
                              <Eye className="h-4 w-4" />
                            </button>
                          </TooltipTrigger>
                          <TooltipContent>View</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <button
                              type="button"
                              onClick={() => setEditUser(u)}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground motion-safe:transition-colors hover:bg-accent hover:text-foreground"
                              aria-label="Edit user"
                            >
                              <Pencil className="h-4 w-4" />
                            </button>
                          </TooltipTrigger>
                          <TooltipContent>Edit</TooltipContent>
                        </Tooltip>
                        {!isArchived && (
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <button
                                type="button"
                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground motion-safe:transition-colors hover:bg-accent hover:text-foreground"
                                aria-label="More actions"
                              >
                                <MoreHorizontal className="h-4 w-4" />
                              </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-40">
                              <DropdownMenuItem
                                variant="destructive"
                                onClick={() => handleArchive(u.account_id)}
                              >
                                <Archive className="mr-2 h-4 w-4" /> Archive
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        )}

        {/* Grid view */}
        {viewMode === 'grid' && (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-tour="users-card">
            {safeUsers.length === 0 ? (
              <div className="col-span-full flex flex-col items-center justify-center py-16 text-center">
                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-muted/60">
                  <Users className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
                </div>
                <p className="text-sm font-semibold text-foreground">No users found</p>
                <p className="mt-1 text-xs text-muted-foreground">Try adjusting your search or filter criteria.</p>
              </div>
            ) : (
              safeUsers.map((u) => (
                <UserCard
                  key={u.account_id}
                  user={u}
                  onView={handleView}
                  onEdit={(user) => setEditUser(user)}
                  onArchive={(user) => handleArchive(user.account_id)}
                />
              ))
            )}
          </div>
        )}
        </div>

        {/* Pagination */}
        <Pagination
          currentPage={pagination?.current_page ?? 1}
          lastPage={pagination?.last_page ?? 1}
          currentCount={safeUsers.length}
          total={pagination?.total ?? 0}
          itemLabel="users"
          alwaysShow
          onPageChange={(nextPage) => fetchUsers({ page: nextPage })}
        />

        {/* Dialogs */}
        <AddUserDialog
          open={showAddUser}
          setOpen={setShowAddUser}
          roles={roles}
          statuses={statuses}
          onSubmitted={() => router.reload({ only: ['users'] })}
        />

        <EditUserDialog
          open={!!editUser}
          setOpen={(v) => !v && setEditUser(null)}
          user={editUser}
          roles={roles}
          statuses={statuses}
          onSubmitted={() => router.reload({ only: ['users'] })}
        />

        {/* View user */}
        <ViewUserDialog
          open={!!viewUser}
          user={viewUser}
          onClose={() => setViewUser(null)}
          onEditClick={(u) => {
            setViewUser(null);
            setEditUser(u);
          }}
        />

        {/* Archive confirmation */}
        <AlertDialog open={archiveTarget !== null} onOpenChange={(v: boolean) => !v && setArchiveTarget(null)}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Archive this user?</AlertDialogTitle>
              <AlertDialogDescription>
                The account will be archived and the user will lose access. You can restore it later.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                onClick={confirmArchive}
              >
                Archive
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </AppLayout>
  );
}
