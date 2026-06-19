import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { ReactNode } from 'react';
import { NotificationBell } from '@/components/NotificationBell';
import { HeaderUserMenu } from '@/components/header-user-menu';
import { Menu } from 'lucide-react';

interface AppSidebarHeaderProps {
  breadcrumbs?: BreadcrumbItemType[];
  title?: string;
  subtitle?: ReactNode;
  actions?: ReactNode;
}

export function AppSidebarHeader({
  breadcrumbs = [],
  title,
  subtitle,
  actions,
}: AppSidebarHeaderProps) {
  return (
    <header className="border-b border-border/60 bg-background/80 backdrop-blur-sm">
      <div className="mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8 py-4">
        {/* Row with toggle, title, and actions */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            {/* Enhanced hamburger trigger - visible on all breakpoints */}
            <SidebarTrigger
              className="-ml-1"
              aria-label="Toggle navigation menu"
            >
              <Menu className="h-5 w-5" />
              <span className="sr-only">Toggle Sidebar</span>
            </SidebarTrigger>

            <div>
              {title && (
                <h1 className="text-xl sm:text-2xl font-bold text-foreground leading-tight tracking-tight">
                  {title}
                </h1>
              )}
              {subtitle && (
                typeof subtitle === 'string' ? (
                  <p className="text-sm text-muted-foreground mt-0.5">{subtitle}</p>
                ) : (
                  <div className="mt-1">{subtitle}</div>
                )
              )}
            </div>
          </div>
          <div className="flex items-center gap-2">
            <NotificationBell />
            <HeaderUserMenu />
            {actions}
          </div>
        </div>

        {/* Breadcrumbs below title */}
        {breadcrumbs.length > 0 && (
          <div className="mt-3">
            <Breadcrumbs breadcrumbs={breadcrumbs} />
          </div>
        )}
      </div>
    </header>
  );
}
