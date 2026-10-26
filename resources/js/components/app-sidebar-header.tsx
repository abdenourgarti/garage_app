import { Link, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { GlobalSearch } from '@/components/global-search';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { fr } from '@/lib/i18n/fr';
import { dashboard } from '@/routes';
import { create as createServiceOrder } from '@/routes/service-orders';

export function AppSidebarHeader() {
    const { auth, name } = usePage().props;
    const [searching, setSearching] = useState(false);
    const [searchCount, setSearchCount] = useState(0);

    const openSearch = () => {
        setSearchCount((count) => count + 1);
        setSearching(true);
    };

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                openSearch();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b bg-background/95 px-3 backdrop-blur md:rounded-t-xl md:px-6">
            <Link
                href={dashboard()}
                className="flex items-center gap-2 font-semibold md:hidden"
                aria-label={name}
            >
                <span className="flex size-9 items-center justify-center rounded-md bg-sidebar text-sidebar-primary">
                    <AppLogoIcon className="size-5" />
                </span>
            </Link>

            <Button
                variant="ghost"
                size="icon-lg"
                className="md:hidden"
                onClick={openSearch}
                aria-label={fr.nav.search}
            >
                <Search className="size-5" />
            </Button>

            <Button
                variant="outline"
                className="hidden h-10 w-72 justify-start gap-2 font-normal text-muted-foreground md:flex"
                onClick={openSearch}
            >
                <Search className="size-4" />
                <span className="truncate">{fr.common.searchPlaceholder}</span>
                <kbd className="ms-auto rounded border bg-muted px-1.5 text-xs">
                    Ctrl K
                </kbd>
            </Button>

            <div className="ms-auto flex items-center gap-2">
                <Button asChild variant="highlight" className="h-11 px-4">
                    <Link href={createServiceOrder()} prefetch>
                        <Plus className="size-5" />
                        <span className="sm:hidden">
                            {fr.nav.newServiceOrderShort}
                        </span>
                        <span className="hidden sm:inline">
                            {fr.nav.newServiceOrder}
                        </span>
                    </Link>
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-lg"
                            className="rounded-full"
                            aria-label={fr.user.menu}
                            data-test="user-menu-button"
                        >
                            <UserAvatar user={auth.user} />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-60" align="end">
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <GlobalSearch
                key={searchCount}
                open={searching}
                onOpenChange={setSearching}
            />
        </header>
    );
}
