import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

export function HeaderUserMenu() {
  const { auth } = usePage<SharedData>().props;

  // Normalize profile: null -> undefined (satisfies UserWithProfile)
  const rawUser = auth.user;
  const rawProfile = rawUser?.profile ?? undefined;

  // Prefer user.avatar; else use profile.profile_picture_url, then profile_picture fallback.
  const resolvedAvatar: string | undefined =
    rawUser?.avatar ??
    (rawProfile?.profile_picture_url
      ? rawProfile.profile_picture_url
      : rawProfile?.profile_picture
      ? rawProfile.profile_picture.startsWith('http') || rawProfile.profile_picture.startsWith('/')
        ? rawProfile.profile_picture
        : `/storage/${rawProfile.profile_picture}`
      : undefined);

  // Build the shape UserMenuContent expects (no null profile)
  const userForMenu = {
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

  const displayName =
    userForMenu.name ??
    `${userForMenu.profile?.first_name ?? ''} ${userForMenu.profile?.last_name ?? ''}`.trim();

  const getInitials = useInitials();
  const initials = getInitials(displayName);

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button className="flex items-center gap-2 rounded-full border border-primary/15 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent p-1 pr-2.5 shadow-sm transition-all duration-200 hover:-translate-y-px hover:from-primary/15 hover:via-primary/10 hover:to-primary/5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
          <Avatar className="h-8 w-8 aspect-square overflow-hidden rounded-full">
            <AvatarImage
              src={userForMenu.avatar}
              alt={displayName}
              className="h-full w-full object-cover object-center"
            />
            <AvatarFallback className="rounded-full bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
              {initials}
            </AvatarFallback>
          </Avatar>

          <div className="hidden min-w-0 text-left sm:block">
            <p className="truncate text-sm font-medium leading-tight text-foreground">{displayName}</p>
            <p className="truncate text-xs leading-tight text-muted-foreground">{userForMenu.email}</p>
          </div>

          <ChevronDown className="h-4 w-4 text-muted-foreground" />
        </button>
      </DropdownMenuTrigger>

      <DropdownMenuContent
        className="w-56 rounded-lg"
        align="end"
        side="bottom"
      >
        <UserMenuContent user={userForMenu} />
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
