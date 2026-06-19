import { usePage } from "@inertiajs/react";
import React from "react";

interface RBACProps {
  permission: string;
  children: React.ReactNode;
}

export function RBAC({ permission, children }: RBACProps) {
  const permissions: string[] =
    (usePage().props as any)?.auth?.user?.permissions || [];

  if (!permissions.includes(permission)) return null;

  return <>{children}</>;
}
