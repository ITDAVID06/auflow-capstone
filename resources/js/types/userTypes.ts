/**
 * User Type Definitions
 * 
 * Strict TypeScript interfaces for authenticated user data shared via Inertia's usePage hook.
 * Based on Laravel backend schema and AppServiceProvider Inertia::share implementation.
 */

/**
 * Authenticated user with permissions
 * This is the exact shape returned by AppServiceProvider's Inertia::share('auth')
 */
export interface AuthUser {
  /** User account ID from tbl_user */
  id: number;
  
  /** User email address */
  email: string;
  
  /** Full name (concatenated from profile: first_name + last_name) */
  name: string;

  /** Resolved profile picture URL for avatar rendering */
  avatar?: string;
  
  /** User profile information */
  profile: UserProfile | null;
  
  /** Directly assigned roles with their permissions */
  roles: UserRole[];
  
  /** Flat array of permission names (computed via allPermissions() method) */
  permissions: string[];
}

/**
 * User profile from tbl_userprofile
 */
export interface UserProfile {
  id: number;
  account_id: number;
  first_name: string;
  last_name: string;
  middle_name?: string | null;
  student_id?: string | null;
  employee_id?: string | null;
  phone?: string | null;
  address?: string | null;
  date_of_birth?: string | null;
  gender?: string | null;
  profile_picture?: string | null;
  profile_picture_url?: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Role with nested permissions from tbl_role
 */
export interface UserRole {
  id: number;
  role_name: string;
  description?: string | null;
  is_active: boolean;
  permissions: RolePermission[];
}

/**
 * Permission nested within role
 */
export interface RolePermission {
  id: number;
  permission_name: string;
  description?: string | null;
  resource: string;
  action: string;
}

/**
 * Auth context shape passed via Inertia shared props
 */
export interface Auth {
  user: AuthUser;
}

/**
 * Type guard to check if user has a specific permission
 */
export function hasPermission(user: AuthUser | null | undefined, permission: string): boolean {
  if (!user) return false;
  return user.permissions.includes(permission);
}

/**
 * Type guard to check if user has any of the specified permissions
 */
export function hasAnyPermission(user: AuthUser | null | undefined, permissions: string[]): boolean {
  if (!user) return false;
  return permissions.some(permission => user.permissions.includes(permission));
}

/**
 * Type guard to check if user has all of the specified permissions
 */
export function hasAllPermissions(user: AuthUser | null | undefined, permissions: string[]): boolean {
  if (!user) return false;
  return permissions.every(permission => user.permissions.includes(permission));
}

/**
 * Get user's display name with fallback
 */
export function getUserDisplayName(user: AuthUser | null | undefined): string {
  if (!user) return 'Guest';
  return user.name || `${user.profile?.first_name || ''} ${user.profile?.last_name || ''}`.trim() || 'User';
}

/**
 * Get user's first name with fallback
 */
export function getUserFirstName(user: AuthUser | null | undefined): string {
  if (!user) return 'Guest';
  return user.profile?.first_name || user.name.split(' ')[0] || 'User';
}
