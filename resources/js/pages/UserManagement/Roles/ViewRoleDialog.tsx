import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Shield, Pencil } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface RolePermissionItem {
    id: number;
    permission_name: string;
    slug: string;
    resource: string;
    action: string;
}

export interface RoleForView {
    id: number;
    role_name: string;
    description: string;
    is_active: boolean;
    permissions_count: number;
    users_count: number;
    permissions: RolePermissionItem[];
}

interface Props {
    role: RoleForView | null;
    open: boolean;
    onClose: () => void;
}

export default function ViewRoleDialog({ role, open, onClose }: Props) {
    const grouped = React.useMemo(() => {
        if (!role) return [];
        const map = new Map<string, RolePermissionItem[]>();
        for (const p of role.permissions) {
            const key = p.resource ? p.resource.charAt(0).toUpperCase() + p.resource.slice(1) : 'General';
            if (!map.has(key)) map.set(key, []);
            map.get(key)!.push(p);
        }
        return Array.from(map.entries()).sort(([a], [b]) => a.localeCompare(b));
    }, [role]);

    if (!role) return null;

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="max-w-2xl p-0 gap-0 max-h-[90vh] overflow-hidden flex flex-col">
                <DialogTitle className="sr-only">
                    {role.role_name} — Permissions
                </DialogTitle>
                <DialogDescription className="sr-only">
                    View all permissions assigned to the {role.role_name} role.
                </DialogDescription>

                {/* ── Hero header ── */}
                <div className="flex items-start gap-4 border-b border-border/60 px-6 py-5">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted/60">
                        <Shield className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
                    </div>

                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-base font-semibold leading-tight text-foreground">
                                {role.role_name}
                            </h2>
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
                            <p className="mt-1 text-sm text-muted-foreground">{role.description}</p>
                        )}

                        <div className="mt-2 flex items-center gap-4 text-xs text-muted-foreground">
                            <span>
                                {role.permissions_count}{' '}
                                {role.permissions_count === 1 ? 'permission' : 'permissions'}
                            </span>
                            <span>
                                {role.users_count}{' '}
                                {role.users_count === 1 ? 'user' : 'users'}
                            </span>
                        </div>
                    </div>

                    <Link href={route('user-management.roles.edit', role.id)} className="shrink-0">
                        <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs">
                            <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                            Edit
                        </Button>
                    </Link>
                </div>

                {/* ── Permissions body ── */}
                <div className="flex-1 overflow-y-auto px-6 py-5">
                    {grouped.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-14 text-center">
                            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-muted/60">
                                <Shield className="h-5 w-5 text-muted-foreground" />
                            </div>
                            <p className="text-sm font-medium text-foreground">No permissions assigned</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Edit this role to assign permissions.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {grouped.map(([resource, perms]) => (
                                <div key={resource}>
                                    {/* Group heading */}
                                    <div className="mb-3 flex items-center gap-2">
                                        <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                            {resource}
                                        </span>
                                        <span className="rounded-full bg-muted px-1.5 py-0.5 font-mono text-[11px] tabular-nums text-muted-foreground">
                                            {perms.length}
                                        </span>
                                    </div>

                                    {/* Permission chips */}
                                    <div className="flex flex-wrap gap-2">
                                        {perms.map((p) => (
                                            <div
                                                key={p.id}
                                                className="flex flex-col gap-0.5 rounded-md border border-border/60 bg-muted/30 px-2.5 py-1.5"
                                            >
                                                <span className="text-xs font-medium leading-none text-foreground">
                                                    {p.permission_name}
                                                </span>
                                                <span className="font-mono text-[10px] leading-none text-muted-foreground">
                                                    {p.slug}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
