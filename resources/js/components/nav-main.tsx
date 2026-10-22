import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useIsNavActive } from '@/lib/navigation';
import type { NavItem } from '@/types';

export function NavMain({ items }: { items: NavItem[] }) {
    const isNavActive = useIsNavActive();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu className="gap-1">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isNavActive(item)}
                            tooltip={{ children: item.title }}
                            className="text-sidebar-foreground/80 data-[active=true]:shadow-[inset_3px_0_0_var(--sidebar-primary)]"
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
