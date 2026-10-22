import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { fr } from '@/lib/i18n/fr';
import type { User } from '@/types';

export function UserAvatar({ user }: { user: User }) {
    const getInitials = useInitials();

    return (
        <Avatar className="size-9 overflow-hidden rounded-full">
            <AvatarFallback className="bg-secondary font-medium text-secondary-foreground">
                {getInitials(user.name)}
            </AvatarFallback>
        </Avatar>
    );
}

export function UserInfo({ user }: { user: User }) {
    return (
        <>
            <UserAvatar user={user} />
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                <span className="truncate text-xs text-muted-foreground">
                    {fr.user.roles[user.role]}
                </span>
            </div>
        </>
    );
}
