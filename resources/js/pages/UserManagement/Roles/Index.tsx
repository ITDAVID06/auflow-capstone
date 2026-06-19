import * as React from 'react';
import { Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Plus, Pencil, Trash2, Shield, Search, CheckCircle2, Clock4, Eye } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
import { cn } from '@/lib/utils';
import ViewRoleDialog, { type RolePermissionItem } from './ViewRoleDialog';

interface Role {
    id: number;
    role_name: string;
    description: string;
    is_active: boolean;
    permissions_count: number;
    users_count: number;
    permissions: RolePermissionItem[];
}

interface Props {
    roles: Role[];
    can: { create: boolean };
}

export default function RolesIndex({ roles, can }: Props) {
    const [q, setQ] = React.useState(() => new URLSearchParams(location.search).get('q') ?? '');
    const [status, setStatus] = React.useState<'all' | 'active' | 'inactive'>(() => {
        const s = new URLSearchParams(location.search).get('status');
        return s === 'active' || s === 'inactive' ? s : 'all';
    });
    const [deleteTarget, setDeleteTarget] = React.useState<Role | null>(null);
    const [deletingId, setDeletingId] = React.useState<number | null>(null);
    const [viewTarget, setViewTarget] = React.useState<Role | null>(null);

    React.useEffect(() => {
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (status !== 'all') params.set('status', status);
        const qs = params.toString();
        history.replaceState(null, '', location.pathname + (qs ? `?${qs}` : ''));
    }, [q, status]);

    const handleDelete = (role: Role) => {
        setDeleteTarget(role);
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        setDeletingId(deleteTarget.id);
        router.delete(route('user-management.roles.destroy', deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => toast.success('Role deleted'),
            onError: (errors) => {
                const msg = Object.values(errors)[0];
                toast.error(typeof msg === 'string' ? msg : 'Failed to delete role');
            },
            onFinish: () => setDeletingId(null),
        });
        setDeleteTarget(null);
    };

    const filtered = React.useMemo(() => {
        const needle = q.trim().toLowerCase();
        return roles.filter((r) => {
            const matchesSearch =
                !needle ||
                r.role_name.toLowerCase().includes(needle) ||
                (r.description || '').toLowerCase().includes(needle);
            const matchesStatus =
                status === 'all' ? true : status === 'active' ? r.is_active : !r.is_active;
            return matchesSearch && matchesStatus;
        });
    }, [roles, q, status]);

    const stats = React.useMemo(() => {
        const total = roles.length;
        const active = roles.filter((r) => r.is_active).length;
        return { total, active, inactive: total - active };
    }, [roles]);

    return (
        <>
        <AppLayout title="Manage Roles" subtitle="Create and manage roles with assigned permissions.">
            <Head title="Manage Roles" />

            <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
                {/* Metric belt */}
                <dl className="grid grid-cols-3 overflow-hidden rounded-lg border border-border/60 bg-card" aria-label="Role metrics">
                  <div className="flex flex-col gap-1.5 px-5 py-5">
                    <dt className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                      <Shield className="h-3.5 w-3.5 text-muted-foreground" aria-hidden="true" />
                      Total Roles
                    </dt>
                    <dd className="text-3xl font-semibold tabular-nums leading-none text-foreground">
                      {stats.total.toLocaleString()}
                    </dd>
                  </div>
                  <div className="flex flex-col gap-1.5 px-5 py-5 border-l border-border/60">
                    <dt className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                      <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500 dark:text-emerald-400" aria-hidden="true" />
                      Active
                    </dt>
                    <dd className="text-3xl font-semibold tabular-nums leading-none text-emerald-700 dark:text-emerald-400">
                      {stats.active.toLocaleString()}
                    </dd>
                  </div>
                  <div className="flex flex-col gap-1.5 px-5 py-5 border-l border-border/60">
                    <dt className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                      <Clock4 className="h-3.5 w-3.5 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                      Inactive
                    </dt>
                    <dd className="text-3xl font-semibold tabular-nums leading-none text-amber-700 dark:text-amber-400">
                      {stats.inactive.toLocaleString()}
                    </dd>
                  </div>
                </dl>

                {/* Toolbar */}
                <div className="flex items-center gap-2">
                    <div className="relative w-64 flex-none">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search roles…"
                            className="h-9 pl-9"
                        />
                    </div>
                    <Select value={status} onValueChange={(v) => setStatus(v as typeof status)}>
                        <SelectTrigger className="h-9 w-[140px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent align="start">
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="active">Active Only</SelectItem>
                            <SelectItem value="inactive">Inactive Only</SelectItem>
                        </SelectContent>
                    </Select>
                    {can.create && (
                        <Button asChild size="sm" className="ml-auto h-9 shrink-0">
                            <Link href={route('user-management.roles.create')}>
                                <Plus className="mr-1.5 h-4 w-4" /> Add Role
                            </Link>
                        </Button>
                    )}
                </div>

                {/* List */}
                <div className="overflow-hidden rounded-lg border border-border/60 bg-card">
                    <div className="hidden sm:grid grid-cols-[minmax(0,1fr)_120px] gap-4 border-b border-border/60 bg-muted/40 px-5 py-2.5">
                        <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Role</span>
                        <span className="sr-only">Actions</span>
                    </div>

                    {filtered.length === 0 ? (
                        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
                            <div className="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-muted/60">
                                <Shield className="h-5 w-5 text-muted-foreground" />
                            </div>
                            <p className="text-sm font-semibold text-muted-foreground">
                                {q || status !== 'all' ? 'No roles match your search' : 'No roles yet'}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {q || status !== 'all'
                                    ? 'Try adjusting your search or filter'
                                    : 'Get started by creating your first role'}
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-border/50">
                            {filtered.map((role) => (
                                <div
                                    key={role.id}
                                    className="group grid cursor-default grid-cols-1 gap-y-1 px-5 py-3 sm:grid-cols-[minmax(0,1fr)_120px] sm:items-center sm:gap-4 sm:gap-y-0 hover:bg-accent/30 motion-safe:transition-colors"
                                >
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-sm font-semibold text-foreground">{role.role_name}</span>
                                            <span
                                                className={cn(
                                                    'inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium',
                                                    role.is_active
                                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                        : 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
                                                )}
                                            >
                                                {role.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                        {role.description && (
                                            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{role.description}</p>
                                        )}
                                        <div className="mt-1 flex items-center gap-4 text-xs text-muted-foreground">
                                            <span>
                                                {role.permissions_count} {role.permissions_count === 1 ? 'permission' : 'permissions'}
                                            </span>
                                            <span>
                                                {role.users_count} {role.users_count === 1 ? 'user' : 'users'}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-end gap-0.5" onClick={(e) => e.stopPropagation()}>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <button
                                                    type="button"
                                                    onClick={() => setViewTarget(role)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground motion-safe:transition-colors hover:bg-accent hover:text-foreground"
                                                    aria-label={`View ${role.role_name} permissions`}
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent>View</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Link
                                                    href={route('user-management.roles.edit', role.id)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground motion-safe:transition-colors hover:bg-accent hover:text-foreground"
                                                    aria-label={`Edit ${role.role_name}`}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </TooltipTrigger>
                                            <TooltipContent>Edit</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(role)}
                                                    disabled={deletingId === role.id}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground motion-safe:transition-colors hover:bg-red-50 hover:text-red-600 disabled:pointer-events-none disabled:opacity-40 dark:hover:bg-red-950/20 dark:hover:text-red-400"
                                                    aria-label={`Delete ${role.role_name}`}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent>Delete</TooltipContent>
                                        </Tooltip>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                {/* Roles are expected to be few (<100); client-side filtering is intentional.
                     Add server-side pagination here if role count grows significantly. */}
                {(q || status !== 'all') && filtered.length !== roles.length && (
                    <p className="px-5 pb-3 text-xs text-muted-foreground">
                        Showing {filtered.length} of {roles.length} roles
                    </p>
                )}
            </div>
        </div>
    </AppLayout>

        <AlertDialog open={deleteTarget !== null} onOpenChange={(v: boolean) => !v && setDeleteTarget(null)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete &ldquo;{deleteTarget?.role_name}&rdquo;?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This cannot be undone. All permission assignments for this role will be removed.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        onClick={confirmDelete}
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <ViewRoleDialog
            role={viewTarget}
            open={viewTarget !== null}
            onClose={() => setViewTarget(null)}
        />
        </>
    );
}
