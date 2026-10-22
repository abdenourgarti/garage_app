import { Head, usePage } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { fr } from '@/lib/i18n/fr';

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={fr.dashboard.title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader title={fr.dashboard.greeting(auth.user.name)} />
                <EmptyState
                    icon={LayoutDashboard}
                    title={fr.common.comingSoon}
                    description={fr.dashboard.description}
                />
            </div>
        </>
    );
}
