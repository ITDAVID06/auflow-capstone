import * as React from 'react';
import { Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import type { RequestPayload } from '@inertiajs/core';
import { toast } from 'sonner';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import RoleForm, { PermissionGroup, RoleFormValues } from './RoleForm';

interface RoleData {
    id: number;
    role_name: string;
    description: string;
    is_active: boolean;
    permission_ids: number[];
}

interface Props {
    role: RoleData;
    permissionGroups: PermissionGroup[];
}

export default function RolesEdit({ role, permissionGroups }: Props) {
    const [processing, setProcessing] = React.useState(false);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [values, setValues] = React.useState<RoleFormValues>({
        role_name: role.role_name,
        description: role.description ?? '',
        is_active: role.is_active,
        permission_ids: role.permission_ids ?? [],
    });

    const handleSubmit = () => {
        setProcessing(true);
        setErrors({});
        router.put(`/user-management/roles/${role.id}`, values as unknown as RequestPayload, {
            onSuccess: () => toast.success('Role updated successfully'),
            onError: (errs) => {
                toast.error('Failed to update role. Please check the form and try again.');
                setErrors(errs);
                setProcessing(false);
            },
        });
    };

    return (
        <AppLayout
            title={`Edit: ${role.role_name}`}
            subtitle="Update role details and permission assignments."
            breadcrumbs={[{ title: 'Roles', href: '/user-management/roles' }, { title: role.role_name, href: '#' }]}
        >
            <Head title={`Edit ${role.role_name}`} />
            <div className="mx-auto w-full max-w-5xl space-y-5 px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
                {/* Back link */}
                <Link
                    href={route('user-management.roles.index')}
                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground motion-safe:transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Back to Roles
                </Link>

                <RoleForm
                    values={values}
                    onChange={setValues}
                    permissionGroups={permissionGroups}
                    errors={errors}
                    processing={processing}
                    submitLabel="Save Changes"
                    onSubmit={handleSubmit}
                    onCancel={() => router.visit(route('user-management.roles.index'))}
                />
            </div>
        </AppLayout>
    );
}
