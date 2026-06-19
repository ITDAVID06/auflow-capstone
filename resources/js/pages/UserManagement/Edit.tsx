import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import UserForm from './components/UserForm';
import type { User, Role, UserStatusOption } from './types';

export default function Edit() {
  const page = usePage().props as unknown as {
    user: User;
    roles: Role[];
    statuses: UserStatusOption[];
  };

  return (
    <AppLayout title="Edit User">
      <Head title="Edit User" />
      <div className="mb-4">
        <h1 className="text-2xl font-semibold">Edit User</h1>
        <p className="text-sm text-muted-foreground">Update user account information</p>
      </div>

      <div className="max-w-2xl">
        <UserForm user={page.user} roles={page.roles} statuses={page.statuses} method="put" onSubmit={() => {}} onClose={() => history.back()} />
      </div>
    </AppLayout>
  );
}
