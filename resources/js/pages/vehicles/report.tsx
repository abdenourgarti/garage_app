import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { fr } from '@/lib/i18n/fr';
import { show as showVehicle } from '@/routes/vehicles';
import { document as reportDocument } from '@/routes/vehicles/report';

const t = fr.report;

/** Aperçu du bilan d'entretien : le document lui-même (comme l'impression et le PDF), avec ou sans les prix. */
export default function VehicleReport({
    vehicle,
}: {
    vehicle: { id: number; plate: string };
}) {
    const [withoutPrices, setWithoutPrices] = useState(false);
    const [previewHeight, setPreviewHeight] = useState(900);
    const url = (format: 'a4' | 'pdf', print = false) =>
        reportDocument.url(
            { vehicle: vehicle.id, format },
            {
                query: {
                    prices: withoutPrices ? 0 : 1,
                    ...(print ? { print: 1 } : {}),
                },
            },
        );

    return (
        <>
            <Head title={`${t.title} — ${vehicle.plate}`} />
            <div className="space-y-4 p-4 md:p-6">
                <Button asChild variant="ghost" className="-ms-3 h-11">
                    <Link href={showVehicle(vehicle.id)}>
                        <ArrowLeft />
                        {t.back}
                    </Link>
                </Button>
                <PageHeader
                    title={`${t.title} — ${vehicle.plate}`}
                    description={t.description}
                    actions={
                        <div className="flex w-full gap-2 sm:w-auto [&>*]:flex-1 sm:[&>*]:flex-none">
                            <Button asChild className="h-11">
                                <a
                                    href={url('a4', true)}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer />
                                    {t.print}
                                </a>
                            </Button>
                            <Button asChild variant="outline" className="h-11">
                                <a href={url('pdf')}>
                                    <Download />
                                    {t.pdf}
                                </a>
                            </Button>
                        </div>
                    }
                />

                <div className="flex items-start gap-3 rounded-xl border bg-card p-4">
                    <Checkbox
                        id="report-without-prices"
                        className="mt-0.5 size-5"
                        checked={withoutPrices}
                        onCheckedChange={(checked) =>
                            setWithoutPrices(checked === true)
                        }
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="report-without-prices">
                            {t.withoutPrices}
                        </Label>
                        <p className="text-sm text-muted-foreground">
                            {t.withoutPricesHint}
                        </p>
                    </div>
                </div>

                {/* Défilement natif : le document est un cadre à sa propre hauteur (pas de Lenis). */}
                <iframe
                    key={String(withoutPrices)}
                    title={t.title}
                    src={url('a4')}
                    className="w-full rounded-xl border bg-white shadow-sm"
                    style={{ height: previewHeight }}
                    onLoad={(event) => {
                        const page =
                            event.currentTarget.contentDocument?.querySelector<HTMLElement>(
                                '.page',
                            );

                        if (page) {
                            setPreviewHeight(page.offsetHeight + 4);
                        }
                    }}
                />
            </div>
        </>
    );
}
