import * as React from 'react';
import { Head, Link } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import type { RequestPayload } from '@inertiajs/core';
import { toast } from 'sonner';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import RoleForm, { PermissionGroup, RoleFormValues } from './RoleForm';

interface Props {
    permissionGroups: PermissionGroup[];
}

export default function RolesCreate({ permissionGroups }: Props) {
    const [processing, setProcessing] = React.useState(false);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [values, setValues] = React.useState<RoleFormValues>({
        role_name: '',
        description: '',
        is_active: true,
        permission_ids: [],
    });

    const handleSubmit = () => {
        setProcessing(true);
        setErrors({});
        router.post('/user-management/roles', values as unknown as RequestPayload, {
            onSuccess: () => toast.success('Role created successfully'),
            onError: (errs) => {
                toast.error('Failed to create role. Please check the form and try again.');
                setErrors(errs);
                setProcessing(false);
            },
        });
    };

    return (
        <AppLayout
            title="Create Role"
            subtitle="Define a new role and assign granular permissions."
            breadcrumbs={[{ title: 'Roles', href: '/user-management/roles' }, { title: 'Create', href: '#' }]}
        >
            <Head title="Create Role" />
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
                    submitLabel="Create Role"
                    onSubmit={handleSubmit}
                    onCancel={() => router.visit(route('user-management.roles.index'))}
                />
            </div>
        </AppLayout>
    );
}
