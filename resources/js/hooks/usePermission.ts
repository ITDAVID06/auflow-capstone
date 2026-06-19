import { usePage } from "@inertiajs/react";

export function usePermission(permissionName: string): boolean {
  const permissions: string[] =
    (usePage().props as any)?.auth?.user?.permissions || [];

  return permissions.includes(permissionName);
}
