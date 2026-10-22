import { Link, router } from '@inertiajs/react';
import { KeyRound, LogOut, Palette, UserRound } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { fr } from '@/lib/i18n/fr';
import { logout } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editPassword } from '@/routes/security';
import type { User } from '@/types';

export function UserMenuContent({ user }: { user: User }) {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const links = [
        { title: fr.user.profile, href: editProfile(), icon: UserRound },
        { title: fr.user.password, href: editPassword(), icon: KeyRound },
        { title: fr.user.appearance, href: editAppearance(), icon: Palette },
    ];

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {links.map((link) => (
                    <DropdownMenuItem
                        key={link.title}
                        asChild
                        className="min-h-10"
                    >
                        <Link
                            className="flex w-full cursor-pointer items-center gap-2"
                            href={link.href}
                            prefetch
                            onClick={cleanup}
                        >
                            <link.icon />
                            {link.title}
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild className="min-h-10">
                <Link
                    className="flex w-full cursor-pointer items-center gap-2"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut />
                    {fr.user.logout}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
