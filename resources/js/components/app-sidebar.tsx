import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { fr } from '@/lib/i18n/fr';
import { useNavigation } from '@/lib/navigation';
import { dashboard } from '@/routes';

export function AppSidebar() {
    const { items } = useNavigation();

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            aria-label={fr.nav.mainMenu}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="pt-2">
                <NavMain items={items} />
            </SidebarContent>
        </Sidebar>
    );
}
