import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

/**
 * SharedData is injected by Inertia and Laravel
 */
export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    [key: string]: unknown;
}

/**
 * Authenticated user wrapper
 */
export interface Auth {
    user: User;
}

/**
 * User core model (tbl_user)
 */
export interface User {
    account_id: number; // matches ERD
    id: number; // keep if your backend returns `id`
    username: string;
    name: string; // often derived: first_name + last_name
    email: string;
    email_verified_at: string | null;
    must_change_password?: boolean;
    avatar?: string;
    created_at: string;
    updated_at: string;
    permissions?: string[];

    // relations
    profile?: UserProfile;
    status?: UserStatus | null;
    roles?: Role[];

    [key: string]: unknown; // keep for flexibility
}

/**
 * User profile model (tbl_userprofile)
 */
export interface UserProfile {
    id: number;
    account_id: number;
    first_name: string;
    last_name: string;
    middle_name?: string;
    student_id?: string;
    employee_id?: string;
    phone?: string;
    address?: string;
    date_of_birth?: string;
    gender?: string;
    profile_picture?: string | null;
    profile_picture_url?: string | null;
    created_at: string;
    updated_at?: string;
}

/**
 * User status model (tbl_user_status)
 */
export interface UserStatus {
    id: number;
    status_name: string;
    description?: string;
}

/**
 * Role (tbl_role)
 */
export interface Role {
    id: number;
    role_name: string;
    description?: string;
    is_active: boolean;
}

/**
 * Permission (tbl_permission)
 */
export interface Permission {
    id: number;
    permission_name: string;
    description?: string;
    resource: string;
    action: string;
}

/**
 * Role ↔ Permission pivot (tbl_role_permission)
 */
export interface RolePermission {
    id: number;
    role_id: number;
    permission_id: number;
    permission?: Permission;
}

/**
 * Breadcrumb item
 */
export interface BreadcrumbItem {
    title: string;
    href: string;
}

/**
 * Navigation
 */
export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}
