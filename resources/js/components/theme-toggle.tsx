import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import { Moon, Sun } from 'lucide-react';
import { useEffect } from 'react';

export default function ThemeToggle() {
    const { appearance, updateAppearance } = useAppearance();

    // Convert system preference to explicit light/dark
    useEffect(() => {
        if (appearance === 'system') {
            const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            updateAppearance(isDark ? 'dark' : 'light');
        }
    }, []);

    const toggleTheme = () => {
        updateAppearance(appearance === 'dark' ? 'light' : 'dark');
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    size="lg"
                    onClick={toggleTheme}
                    className="text-sidebar-accent-foreground"
                    title={`Switch to ${appearance === 'dark' ? 'light' : 'dark'} mode`}
                >
                    {appearance === 'dark' ? (
                        <Sun className="h-4 w-4" />
                    ) : (
                        <Moon className="h-4 w-4" />
                    )}
                    <span className="text-sm font-medium">
                        {appearance === 'dark' ? 'Light Mode' : 'Dark Mode'}
                    </span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
