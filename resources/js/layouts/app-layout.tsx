import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { useFlashToast } from '@/hooks/useFlashToast';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface AppLayoutProps {
  children: ReactNode;
  breadcrumbs?: BreadcrumbItem[];
  title?: string;
  subtitle?: ReactNode;
  actions?: ReactNode;
}

export default function AppLayout({
  children,
  breadcrumbs,
  actions,
  ...props
}: AppLayoutProps) {
  useFlashToast();

  return (
      <AppLayoutTemplate
        breadcrumbs={breadcrumbs}
        actions={
          <div className="flex items-center gap-2">
            {actions}
          </div>
        }
        {...props}
      >
        {children}
      </AppLayoutTemplate>
  );
}
