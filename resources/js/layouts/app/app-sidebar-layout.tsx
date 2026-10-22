import { AppBottomNav } from '@/components/app-bottom-nav';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({ children }: AppLayoutProps) {
    return (
        <AppShell>
            <AppSidebar />
            <AppContent className="min-w-0 overflow-x-clip">
                <AppSidebarHeader />
                {/* pb-24 : place pour la barre basse mobile. */}
                <div className="flex flex-1 flex-col pb-24 md:pb-0">
                    {children}
                </div>
            </AppContent>
            <AppBottomNav />
        </AppShell>
    );
}
