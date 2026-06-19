import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Loader2, Search, Shield } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface PermissionGroupItem {
    id: number;
    permission_name: string;
    slug: string;
}

export interface PermissionGroup {
    group: string;
    permissions: PermissionGroupItem[];
}

export interface RoleFormValues {
    role_name: string;
    description: string;
    is_active: boolean;
    permission_ids: number[];
}

interface Props {
    values: RoleFormValues;
    onChange: (values: RoleFormValues) => void;
    permissionGroups: PermissionGroup[];
    errors?: Record<string, string>;
    processing: boolean;
    submitLabel: string;
    onSubmit: () => void;
    onCancel: () => void;
}

export default function RoleForm({ values, onChange, permissionGroups, errors, processing, submitLabel, onSubmit, onCancel }: Props) {
    const [q, setQ] = React.useState('');

    const set = <K extends keyof RoleFormValues>(key: K, val: RoleFormValues[K]) =>
        onChange({ ...values, [key]: val });

    const allPermissions = permissionGroups.flatMap((g) => g.permissions);

    const visibleIds = React.useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return new Set(allPermissions.map((p) => p.id));
        const result = new Set<number>();
        for (const p of allPermissions) {
            if (`${p.permission_name} ${p.slug}`.toLowerCase().includes(needle)) {
                result.add(p.id);
            }
        }
        return result;
    }, [allPermissions, q]);

    const isChecked = (id: number) => values.permission_ids.includes(id);

    const toggle = (id: number) =>
        set(
            'permission_ids',
            isChecked(id) ? values.permission_ids.filter((x) => x !== id) : [...values.permission_ids, id],
        );

    const selectGroup = (ids: number[]) =>
        set('permission_ids', Array.from(new Set([...values.permission_ids, ...ids])));

    const clearGroup = (ids: number[]) =>
        set('permission_ids', values.permission_ids.filter((id) => !ids.includes(id)));

    const selectAllVisible = () =>
        set('permission_ids', Array.from(new Set([...values.permission_ids, ...Array.from(visibleIds)])));

    const clearAll = () => set('permission_ids', []);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit();
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-5">
            {/* ── Basic Information ── */}
            <div className="space-y-4 rounded-xl border border-border/60 bg-card p-4 md:p-5">
                <div className="border-b border-border/60 pb-2">
                    <h3 className="text-sm font-semibold text-foreground">Basic Information</h3>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="space-y-2 md:col-span-2">
                        <Label htmlFor="role_name" className="text-sm font-medium">
                            Role Name <span className="text-red-500" aria-hidden="true">*</span>
                        </Label>
                        <Input
                            id="role_name"
                            placeholder="e.g., Content Manager, System Admin"
                            value={values.role_name}
                            onChange={(e) => set('role_name', e.target.value)}
                            required
                        />
                        {errors?.role_name && (
                            <p className="flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                                <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                                {errors.role_name}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="role-status" className="text-sm font-medium">Status</Label>
                        <Select
                            value={values.is_active ? 'active' : 'inactive'}
                            onValueChange={(v) => set('is_active', v === 'active')}
                        >
                            <SelectTrigger id="role-status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2 md:col-span-3">
                        <Label htmlFor="role-description" className="text-sm font-medium">Description</Label>
                        <Textarea
                            id="role-description"
                            placeholder="Brief description of this role's purpose and responsibilities…"
                            value={values.description}
                            onChange={(e) => set('description', e.target.value)}
                            rows={3}
                            className="resize-none"
                        />
                        {errors?.description && (
                            <p className="flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                                <span className="inline-block h-1 w-1 rounded-full bg-red-600" aria-hidden="true" />
                                {errors.description}
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {/* ── Permissions ── */}
            <div className="space-y-4 rounded-xl border border-border/60 bg-card p-4 md:p-5">
                <div className="flex items-center justify-between border-b border-border/60 pb-2">
                    <h3 className="text-sm font-semibold text-foreground">Permissions</h3>
                    <span className="rounded-full bg-muted px-2 py-0.5 font-mono text-xs tabular-nums text-muted-foreground">
                        {values.permission_ids.length} selected
                    </span>
                </div>

                {/* Search + bulk actions */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search by name or slug…"
                            className="pl-9"
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={selectAllVisible}>
                            Select Visible
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={clearAll}>
                            Clear All
                        </Button>
                    </div>
                </div>

                {/* Permission groups */}
                <div className="space-y-3">
                    {permissionGroups.map(({ group, permissions }) => {
                        const ids = permissions.filter((p) => visibleIds.has(p.id)).map((p) => p.id);
                        if (ids.length === 0) return null;
                        const selectedInGroup = ids.filter((id) => values.permission_ids.includes(id)).length;
                        const allSelected = selectedInGroup === ids.length && ids.length > 0;

                        return (
                            <div
                                key={group}
                                className="overflow-hidden rounded-lg border border-border/60 bg-background motion-safe:transition-colors hover:border-border"
                            >
                                {/* Group header */}
                                <div className="flex items-center justify-between border-b border-border/60 bg-muted/40 px-4 py-2.5">
                                    <div className="flex items-center gap-2">
                                        <h4 className="text-sm font-semibold capitalize text-foreground">{group}</h4>
                                        <span
                                            className={cn(
                                                'rounded-full px-1.5 py-0.5 font-mono text-xs tabular-nums',
                                                allSelected
                                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                    : selectedInGroup > 0
                                                      ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                                      : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {selectedInGroup}/{ids.length}
                                        </span>
                                    </div>
                                    <div className="flex gap-1.5">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => selectGroup(ids)}
                                            className="h-6 px-2 text-xs"
                                        >
                                            All
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => clearGroup(ids)}
                                            className="h-6 px-2 text-xs"
                                        >
                                            None
                                        </Button>
                                    </div>
                                </div>

                                {/* Permission items */}
                                <div className="grid gap-0 sm:grid-cols-2 divide-y divide-border/50 sm:divide-x sm:divide-y-0">
                                    {permissions
                                        .filter((p) => visibleIds.has(p.id))
                                        .map((p) => {
                                            const checked = isChecked(p.id);
                                            return (
                                                <label
                                                    key={p.id}
                                                    className={cn(
                                                        'flex cursor-pointer items-start gap-3 px-4 py-3 motion-safe:transition-colors',
                                                        checked
                                                            ? 'bg-accent/50'
                                                            : 'hover:bg-accent/30',
                                                    )}
                                                >
                                                    <Checkbox
                                                        checked={checked}
                                                        onCheckedChange={() => toggle(p.id)}
                                                        className="mt-0.5 shrink-0"
                                                    />
                                                    <div className="min-w-0 flex-1">
                                                        <div className="truncate text-sm font-medium text-foreground">
                                                            {p.permission_name}
                                                        </div>
                                                        <div className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                            {p.slug}
                                                        </div>
                                                    </div>
                                                </label>
                                            );
                                        })}
                                </div>
                            </div>
                        );
                    })}

                    {permissionGroups.every(
                        ({ permissions }) => permissions.filter((p) => visibleIds.has(p.id)).length === 0,
                    ) && (
                        <div className="flex flex-col items-center justify-center rounded-lg border border-border/60 bg-card px-6 py-12 text-center">
                            <div className="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-muted/60">
                                <Shield className="h-5 w-5 text-muted-foreground" />
                            </div>
                            <p className="text-sm font-medium text-muted-foreground">No permissions match your search</p>
                        </div>
                    )}
                </div>
            </div>

            {/* ── Footer ── */}
            <div className="flex items-center justify-end gap-3 pt-1">
                <Button type="button" variant="outline" onClick={onCancel} disabled={processing}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing || !values.role_name.trim()} className="min-w-[120px]">
                    {processing ? (
                        <span className="flex items-center gap-2">
                            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                            Saving…
                        </span>
                    ) : (
                        submitLabel
                    )}
                </Button>
            </div>
        </form>
    );
}
