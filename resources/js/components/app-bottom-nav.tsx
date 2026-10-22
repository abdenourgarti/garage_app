import { Link, usePage } from '@inertiajs/react';
import { LogOut, Menu } from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { UserInfo } from '@/components/user-info';
import { fr } from '@/lib/i18n/fr';
import { useIsNavActive, useNavigation } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

const itemClass =
    'flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-xs font-medium';

/** Barre basse à 5 entrées sous 768 px (docs/04-design-system.md §3). */
export function AppBottomNav() {
    const { auth } = usePage().props;
    const { bottom, more } = useNavigation();
    const isNavActive = useIsNavActive();
    const [open, setOpen] = useState(false);

    const moreIsActive = more.some(isNavActive);

    return (
        <>
            <nav
                aria-label={fr.nav.mainMenu}
                className="fixed inset-x-0 bottom-0 z-30 flex border-t bg-card pb-[env(safe-area-inset-bottom)] md:hidden"
            >
                {bottom.map((item) => {
                    const active = isNavActive(item);

                    return (
                        <Link
                            key={item.title}
                            href={item.href}
                            prefetch
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                itemClass,
                                active
                                    ? 'text-primary'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {item.icon && <item.icon className="size-5" />}
                            {item.shortTitle ?? item.title}
                        </Link>
                    );
                })}
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className={cn(
                        itemClass,
                        moreIsActive ? 'text-primary' : 'text-muted-foreground',
                    )}
                >
                    <Menu className="size-5" />
                    {fr.nav.more}
                </button>
            </nav>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent
                    side="bottom"
                    className="rounded-t-xl pb-[env(safe-area-inset-bottom)]"
                >
                    <SheetHeader>
                        <SheetTitle>{fr.nav.more}</SheetTitle>
                        <SheetDescription className="sr-only">
                            {fr.nav.mainMenu}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-1 px-2 pb-4">
                        {more.map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                onClick={() => setOpen(false)}
                                className={cn(
                                    'flex min-h-12 items-center gap-3 rounded-md px-3 text-base hover:bg-accent',
                                    isNavActive(item) &&
                                        'bg-accent font-medium',
                                )}
                            >
                                {item.icon && (
                                    <item.icon className="size-5 text-muted-foreground" />
                                )}
                                {item.title}
                            </Link>
                        ))}
                        <Link
                            href={editProfile()}
                            onClick={() => setOpen(false)}
                            aria-label={fr.user.menu}
                            className="mt-2 flex min-h-14 items-center gap-3 rounded-md border-t px-3 pt-2 hover:bg-accent"
                        >
                            <UserInfo user={auth.user} />
                        </Link>
                        <Link
                            href={logout()}
                            as="button"
                            onClick={() => setOpen(false)}
                            className="flex min-h-12 items-center gap-3 rounded-md px-3 text-base text-destructive hover:bg-accent"
                        >
                            <LogOut className="size-5" />
                            {fr.user.logout}
                        </Link>
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
