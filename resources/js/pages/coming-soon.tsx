import { Head, Link } from '@inertiajs/react';
import { Hammer } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import { fr } from '@/lib/i18n/fr';
import { dashboard } from '@/routes';

// shortcut: page provisoire des modules non livrés ; supprimée module par module (phases 3 à 14).
export default function ComingSoon({ title }: { title: string }) {
    return (
        <>
            <Head title={title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader title={title} />
                <EmptyState
                    icon={Hammer}
                    title={fr.common.comingSoon}
                    description={fr.common.comingSoonText}
                    action={
                        <Button asChild variant="outline" className="h-11">
                            <Link href={dashboard()}>
                                {fr.common.backToHome}
                            </Link>
                        </Button>
                    }
                />
            </div>
        </>
    );
}
