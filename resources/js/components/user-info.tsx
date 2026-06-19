import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';

interface UserProfile {
  first_name?: string;
  last_name?: string;
}

interface UserWithProfile {
  name?: string;
  email?: string;
  avatar?: string;
  profile?: UserProfile;
}

export function UserInfo({ user, showEmail = false }: { user: UserWithProfile; showEmail?: boolean }) {
  const getInitials = useInitials();

  const displayName =
    user.name ??
    `${user.profile?.first_name ?? ''} ${user.profile?.last_name ?? ''}`.trim();

  const initials = getInitials(displayName);

  return (
    <>
      <Avatar className="h-8 w-8 aspect-square overflow-hidden rounded-full">
  <AvatarImage
    src={user.avatar}
    alt={displayName}
    className="h-full w-full object-cover object-center"
  />
  <AvatarFallback className="rounded-full bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
    {initials}
  </AvatarFallback>
</Avatar>

      <div className="grid flex-1 text-left text-sm leading-tight">
        <span className="truncate font-medium">{displayName}</span>
        {showEmail && (
          <span className="truncate text-xs text-muted-foreground">{user.email}</span>
        )}
      </div>
    </>
  );
}
