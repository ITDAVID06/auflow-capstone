import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { SidebarOverlay } from '@/components/sidebar-overlay';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren, ReactNode } from 'react';

interface AppSidebarLayoutProps {
  breadcrumbs?: BreadcrumbItem[];
  title?: string;
  subtitle?: ReactNode;
  actions?: ReactNode; // Added
}

export default function AppSidebarLayout({
  children,
  breadcrumbs = [],
  title,
  subtitle,
  actions,
}: PropsWithChildren<AppSidebarLayoutProps>) {
  return (
    <AppShell variant="sidebar">
      <AppSidebar />
      <SidebarOverlay />
      <AppContent variant="sidebar" className="overflow-x-hidden">
        <AppSidebarHeader
          breadcrumbs={breadcrumbs}
          title={title}
          subtitle={subtitle}
          actions={actions}
        />
        {children}
      </AppContent>
    </AppShell>
  );
}
