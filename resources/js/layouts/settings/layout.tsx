import { Breadcrumbs } from '@/components/breadcrumbs';
import { cn } from '@/lib/utils';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { User, Lock, Palette } from 'lucide-react';
import { type PropsWithChildren, useMemo } from 'react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: '/settings/profile',
        icon: User,
    },
    {
        title: 'Password',
        href: '/settings/password',
        icon: Lock,
    },
    {
        title: 'Appearance',
        href: '/settings/appearance',
        icon: Palette,
    },
];

/** Map current path to a human-readable settings page title. */
function settingsPageTitle(path: string): string {
    if (path.includes('/profile')) return 'Profile';
    if (path.includes('/password')) return 'Password';
    if (path.includes('/appearance')) return 'Appearance';
    return 'Settings';
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<SharedData>().props;

    // Resolve the correct dashboard URL based on the user's primary permission.
    const dashboardHref = useMemo(() => {
        const perms: string[] = auth?.user?.permissions ?? [];
        if (perms.includes('dashboard.admin')) return '/dashboard';
        if (perms.includes('dashboard.staff')) return '/staff-dashboard';
        if (perms.includes('dashboard.student')) return '/student-dashboard';
        return '/dashboard';
    }, [auth?.user?.permissions]);

    const dashboardLabel = useMemo(() => {
        const perms: string[] = auth?.user?.permissions ?? [];
        if (perms.includes('dashboard.admin')) return 'Admin Dashboard';
        if (perms.includes('dashboard.staff')) return 'Approver Dashboard';
        if (perms.includes('dashboard.student')) return 'Requester Dashboard';
        return 'Dashboard';
    }, [auth?.user?.permissions]);

    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;

    const breadcrumbs = [
        { title: dashboardLabel, href: dashboardHref },
        { title: 'Settings', href: '/settings/profile' },
        { title: settingsPageTitle(currentPath), href: currentPath },
    ];

    return (
        <div className="mx-auto w-full max-w-[1400px] px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            {/* Breadcrumbs */}
            <div className="mb-4">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            {/* Page Header */}
            <div className="mb-6 sm:mb-8">
                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                    Settings
                </h1>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1.5">
                    Manage your account settings and preferences
                </p>
            </div>

            <div className="flex flex-col gap-6 lg:flex-row lg:gap-8">
                {/* Sidebar Navigation */}
                <aside className="w-full lg:w-56 flex-shrink-0">
                    <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                        <div className="px-3 py-3">
                            <p className="px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                                Account
                            </p>
                            <nav className="space-y-0.5">
                                {sidebarNavItems.map((item) => {
                                    const Icon = item.icon;
                                    const isActive = currentPath === item.href;

                                    return (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            className={cn(
                                                'flex items-center gap-2.5 px-3 py-2 text-sm font-medium rounded-lg motion-safe:transition-colors',
                                                isActive
                                                    ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'
                                                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100'
                                            )}
                                        >
                                            {Icon && (
                                                <Icon
                                                    className={cn(
                                                        'h-4 w-4 flex-shrink-0',
                                                        isActive
                                                            ? 'text-blue-600 dark:text-blue-400'
                                                            : 'text-gray-400 dark:text-gray-500'
                                                    )}
                                                />
                                            )}
                                            {item.title}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>
                    </div>
                </aside>

                {/* Main Content */}
                <div className="flex-1 min-w-0">
                    <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                        <div className="px-5 sm:px-6 py-6">
                            {children}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
