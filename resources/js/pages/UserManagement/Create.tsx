import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import UserForm from './components/UserForm';
import type { Role, UserStatusOption } from './types';

export default function Create() {
  const { roles, statuses } = usePage().props as unknown as { roles: Role[]; statuses: UserStatusOption[] };

  return (
    <AppLayout title="Add User">
      <Head title="Add User" />
      <div className="mb-4">
        <h1 className="text-2xl font-semibold">Add User</h1>
        <p className="text-sm text-muted-foreground">Create a new system user</p>
      </div>

      <div className="max-w-2xl">
        <UserForm user={null} roles={roles} statuses={statuses} method="post" onSubmit={() => {}} onClose={() => history.back()} />
      </div>
    </AppLayout>
  );
}
