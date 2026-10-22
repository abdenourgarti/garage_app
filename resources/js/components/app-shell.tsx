import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import { useIsDesktop } from '@/hooks/use-mobile';

/** Sidebar pilotée par la largeur d'écran uniquement : pas de bouton à comprendre pour l'utilisateur. */
export function AppShell({ children }: { children: ReactNode }) {
    const isDesktop = useIsDesktop();

    return (
        <SidebarProvider open={isDesktop} onOpenChange={() => {}}>
            {children}
        </SidebarProvider>
    );
}
