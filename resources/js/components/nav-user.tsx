// FRONTEND
// File: resources/js/components/nav-user.tsx

import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useIsMobile } from '@/hooks/use-mobile';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';

export function NavUser() {
  const { auth } = usePage<SharedData>().props;
  const { state } = useSidebar();
  const isMobile = useIsMobile();

  // Normalize profile: null -> undefined (satisfies UserWithProfile)
  const rawUser = auth.user;
  const rawProfile = rawUser?.profile ?? undefined;

  // Prefer user.avatar; else use profile.profile_picture (prefix /storage if relative)
  const resolvedAvatar: string | undefined =
    rawUser?.avatar ??
    (rawProfile?.profile_picture_url
      ? rawProfile.profile_picture_url
      : rawProfile?.profile_picture
      ? rawProfile.profile_picture.startsWith('http') || rawProfile.profile_picture.startsWith('/')
        ? rawProfile.profile_picture
        : `/storage/${rawProfile.profile_picture}`
      : undefined);

  // Build the shape UserInfo/UserMenuContent expect (no null profile)
  const userForSidebar = {
    name: rawUser?.name as string | undefined,
    email: rawUser?.email as string | undefined,
    avatar: resolvedAvatar,
    profile: rawProfile
      ? ({
          first_name: rawProfile.first_name as string | undefined,
          last_name: rawProfile.last_name as string | undefined,
        })
      : undefined,
  };

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              size="lg"
              className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
            >
              {/* Show email too */}
              <UserInfo user={userForSidebar} showEmail />
              <ChevronsUpDown className="ml-auto size-4" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>

          <DropdownMenuContent
            className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
            align="end"
            side={isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'bottom'}
          >
            {/* Pass normalized user here as well to avoid the same TS error */}
            <UserMenuContent user={userForSidebar} />
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  );
}