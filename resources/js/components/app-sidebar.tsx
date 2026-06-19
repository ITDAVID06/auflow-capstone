import { useMemo } from "react";
import { usePage } from "@inertiajs/react";

import { NavMain } from "@/components/nav-main";
import ThemeToggle from "@/components/theme-toggle";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";

import type { NavItem, SharedData } from "@/types";
import {
  LayoutGrid,
  GraduationCap,
  Briefcase,
  FileText,
  Hourglass,
  ClipboardList,
  FilePlus,
  Share2,
  Folder,
  BookOpen,
  History,
  Shield,
  Bug,
  BarChart2,
} from "lucide-react";
import AppLogo from "./app-logo";

const PERMISSION_KEYS = {
  MANAGE_USERS: "users.manage",
  MANAGE_FORMS: "forms.manage",
  MANAGE_WORKFLOWS: "workflows.manage",
  ACCESS_STUDENT_DASH: "dashboard.student",
  ACCESS_STAFF_DASH: "dashboard.staff",
  ACCESS_ADMIN_DASH: "dashboard.admin",
  MANAGE_FACILITIES: "facilities.manage",
  VIEW_ALL_SUBMISSIONS: "submissions.view",
  OVERRIDE_APPROVALS: "submissions.override",
  MANAGE_ROLES: "roles.manage",
  VIEW_PERFORMANCE: "performance.view",
  MANAGE_ERROR_REPORTS: "error-reports.manage",
} as const;

/** Lightweight section header (denser spacing) */
function SectionLabel({ children }: { children: string }) {
  return (
    <div className="px-3 pt-4 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-sidebar-foreground/40">
      {children}
    </div>
  );
}

export function AppSidebar() {
  const page = usePage<SharedData>();
  const permissions = useMemo<string[]>(
    () => page.props.auth?.user?.permissions ?? [],
    [page.props.auth?.user?.permissions],
  );
  const permissionSet = useMemo(() => new Set(permissions), [permissions]);

  const access = useMemo(() => {
    const has = (perm: string) => permissionSet.has(perm);
    const hasAny = (perms: string[]) => perms.some((permission) => has(permission));

    return {
      canAccessAdminDashboard: has(PERMISSION_KEYS.ACCESS_ADMIN_DASH),
      canAccessStudentDashboard: has(PERMISSION_KEYS.ACCESS_STUDENT_DASH),
      canAccessStaffDashboard: has(PERMISSION_KEYS.ACCESS_STAFF_DASH),
      canManageForms: has(PERMISSION_KEYS.MANAGE_FORMS),
      canManageWorkflows: has(PERMISSION_KEYS.MANAGE_WORKFLOWS),
      canManageFacilities: has(PERMISSION_KEYS.MANAGE_FACILITIES),
      canManageRoles: hasAny([PERMISSION_KEYS.MANAGE_USERS, PERMISSION_KEYS.MANAGE_ROLES]),
      canAccessUserManagement: hasAny([PERMISSION_KEYS.MANAGE_USERS, PERMISSION_KEYS.MANAGE_ROLES]),
      canAccessAuditTrail: has(PERMISSION_KEYS.MANAGE_USERS),
      canSeeAllSubs: hasAny([PERMISSION_KEYS.VIEW_ALL_SUBMISSIONS, PERMISSION_KEYS.OVERRIDE_APPROVALS]),
      canViewPerformance: has(PERMISSION_KEYS.VIEW_PERFORMANCE),
      canManageErrorReports: has(PERMISSION_KEYS.MANAGE_ERROR_REPORTS),
    };
  }, [permissionSet]);

  type Section = { title: string; items: NavItem[] };

  const sections: Section[] = useMemo(() => {
    const dashboards: NavItem[] = [];
    if (access.canAccessAdminDashboard) {
      dashboards.push({ title: "Admin Dashboard", href: "/dashboard", icon: LayoutGrid });
    }
    if (access.canAccessStudentDashboard) {
      dashboards.push({ title: "Requester Dashboard", href: "/student-dashboard", icon: GraduationCap });
    }
    if (access.canAccessStaffDashboard) {
      dashboards.push({ title: "Approver Dashboard", href: "/staff-dashboard", icon: Briefcase });
    }

    const approvalsAndSubs: NavItem[] = [];
    if (access.canSeeAllSubs) {
      approvalsAndSubs.push({ title: "Pending Approvals", href: "/admin/submissions/pending", icon: Hourglass });
    }
    if (access.canSeeAllSubs) {
      approvalsAndSubs.push({ title: "All Submissions", href: "/admin/submissions", icon: ClipboardList });
    }

    const requests: NavItem[] = [];
    // Combine Request Forms into single tab (both Student and Staff use same route)
    if (access.canAccessStudentDashboard || access.canAccessStaffDashboard) {
      const href = access.canAccessStudentDashboard
        ? "/student-dashboard/forms"
        : "/staff-dashboard/forms";
      requests.push({ title: "Request Forms", href, icon: FileText });
    }

    const build: NavItem[] = [];
    // Show Form Management only to users with Manage Forms permission (Admins)
    if (access.canManageForms) {
      build.push({ title: "Form Management", href: "/admin/forms", icon: FilePlus });
    }
    if (access.canManageWorkflows) {
      build.push({ title: "Workflow Management", href: "/admin/workflows", icon: Share2 });
    }

    const management: NavItem[] = [];
    if (access.canAccessUserManagement) {
      management.push({ title: "Manage Users", href: "/user-management/users", icon: Folder });
    }
    if (access.canManageRoles) {
      management.push({ title: "Manage Roles", href: "/user-management/roles", icon: Shield });
    }

    const facilities: NavItem[] = [];
    if (access.canManageFacilities) {
      facilities.push({ title: "Facility Calendar", href: "/admin/facilities/calendar", icon: BookOpen });
    }

    const reports: NavItem[] = [];
    // Reports
    if (access.canSeeAllSubs) {
      reports.push({ title: "Reports", href: "/reports", icon: BarChart2 });
    }
    // Audit Trail
    if (access.canAccessAuditTrail) {
      reports.push({ title: "Audit Trail", href: "/admin/audit-trail", icon: History });
    }
    // Performance
    if (access.canViewPerformance) {
      reports.push({ title: "Staff Performance", href: "/performance", icon: Hourglass });
    }
    // Bug Reports
    if (access.canManageErrorReports) {
      reports.push({ title: "Bug Reports", href: "/admin/error-reports", icon: Bug });
    }

    const s: Section[] = [];
    if (dashboards.length) s.push({ title: "Dashboards", items: dashboards });
    if (approvalsAndSubs.length) s.push({ title: "Approvals & Submissions", items: approvalsAndSubs });
    if (requests.length) s.push({ title: "Requests", items: requests });
    if (build.length) s.push({ title: "Build", items: build });
    if (management.length) s.push({ title: "Management", items: management });
    if (facilities.length) s.push({ title: "Facilities", items: facilities });
    if (reports.length) s.push({ title: "Reports & Analytics", items: reports });

    return s;
  }, [
    access,
  ]);

  return (
    <Sidebar collapsible="offcanvas" variant="inset" data-tour="sidebar">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <div>
                <AppLogo className="select-none cursor-default" />
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {sections.map((section) => (
          <div key={section.title} className="mb-1">
            <SectionLabel>{section.title}</SectionLabel>
            <NavMain items={section.items} density="compact" />
          </div>
        ))}
      </SidebarContent>

      <SidebarFooter data-tour="sidebar-user">
        <ThemeToggle />
      </SidebarFooter>
    </Sidebar>
  );
}