import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

/**
 * SidebarOverlay - Provides a backdrop when sidebar is open on desktop
 * Only renders on desktop (hidden on mobile where Sheet handles its own overlay)
 */
export function SidebarOverlay() {
  const { open, isMobile } = useSidebar();

  // Only show overlay on desktop when sidebar is open
  if (isMobile || !open) return null;

  return (
    <div
      className={cn(
        "fixed inset-0 z-[9] bg-transparent transition-opacity duration-200 ease-linear md:block hidden pointer-events-none",
        open ? "opacity-100" : "opacity-0"
      )}
      aria-hidden="true"
    />
  );
}
