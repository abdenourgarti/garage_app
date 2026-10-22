import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { fr } from '@/lib/i18n/fr';

export default function Appearance() {
    return (
        <>
            <Head title={fr.settings.appearanceTitle} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={fr.settings.appearanceTitle}
                    description={fr.settings.appearanceDescription}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}
