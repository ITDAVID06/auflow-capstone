export interface Permission {
  id: number;
  permission_name: string;
  resource: string;
  action: string;
  description?: string;
}

export interface Role {
  id: number;
  role_name: string;
  description: string;
  is_active: boolean;
  permissions?: Permission[];
}

export interface UserStatusOption {
  id: number;
  status_name: string;
}

export interface UserProfile {
  first_name: string;
  last_name: string;
  middle_name?: string;
  employee_id?: string;
  student_id?: string;
  phone?: string;
  address?: string;
  date_of_birth?: string;
  gender?: string;
  profile_picture?: string | null;
  profile_picture_url?: string | null;
}

export interface Status {
  id: number;
  status_name: string;
}

export interface UserRole {
  role_id: number;
  role_name: string;
}

export interface User {
  account_id: number;
  name: string; // username
  email: string;
  created_at: string;
  status: Status;
  profile: UserProfile;
  roles: UserRole[];
}
