import { Link } from '@inertiajs/react';
import { FileText, Wrench } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { Plate } from '@/components/app/plate';
import { StatusBadge } from '@/components/app/status-badge';
import { formatDate, formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { show as showInvoice } from '@/routes/invoices';
import { show as showOrder } from '@/routes/service-orders';
import type { InvoiceStatus } from '@/types/invoices';

export type HistoryRow = {
    id: number;
    number: string;
    date: string;
    mileage: number | null;
    status: 'en_cours' | 'terminee';
    plate: string;
    works: string;
    types: string[];
    invoice: { id: number; number: string; status: InvoiceStatus } | null;
    advice: string | null;
    total: string;
};

const t = fr.serviceOrders;

/**
 * Carnet d'entretien (fiche véhicule) ou prestations d'un client : chronologie en cartes, la plus récente en haut,
 * avec un filtre par type de travail (ex. toutes les vidanges).
 */
export function OrderHistory({
    rows,
    showPlate = false,
}: {
    rows: HistoryRow[];
    showPlate?: boolean;
}) {
    const [type, setType] = useState<string | null>(null);

    if (rows.length === 0) {
        return <EmptyState icon={Wrench} title={t.logbookEmpty} />;
    }

    const types = [...new Set(rows.flatMap((row) => row.types))].sort();
    const shown = type ? rows.filter((row) => row.types.includes(type)) : rows;

    return (
        <div className="space-y-3">
            {types.length > 1 && (
                <div
                    role="group"
                    aria-label={t.logbookFilter}
                    className="flex flex-wrap gap-2"
                >
                    {[null, ...types].map((value) => (
                        <button
                            key={value ?? 'all'}
                            type="button"
                            aria-pressed={type === value}
                            onClick={() => setType(value)}
                            className={cn(
                                'min-h-11 rounded-full border px-4 text-sm font-medium transition-colors hover:border-primary/40',
                                type === value
                                    ? 'border-primary bg-primary text-primary-foreground hover:border-primary'
                                    : 'bg-card',
                            )}
                        >
                            {value ?? t.logbookAll(rows.length)}
                        </button>
                    ))}
                </div>
            )}
            <ol className="relative grid gap-3 border-s-2 border-border ps-4 sm:ps-6">
                {shown.map((row) => (
                    <li key={row.id} className="relative">
                        <span
                            aria-hidden="true"
                            className={cn(
                                'absolute -start-[1.4rem] top-4 size-3 rounded-full border-2 border-background sm:-start-[1.9rem]',
                                row.status === 'en_cours'
                                    ? 'bg-highlight'
                                    : 'bg-primary',
                            )}
                        />
                        <article className="grid gap-2 rounded-xl border bg-card p-3 sm:p-4">
                            <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <Link
                                    href={showOrder(row.id)}
                                    className="font-semibold tabular-nums hover:underline"
                                >
                                    {formatDate(row.date)}
                                    {row.mileage !== null && (
                                        <span className="ms-2 font-normal text-muted-foreground">
                                            {formatQuantity(row.mileage, 'km')}
                                        </span>
                                    )}
                                </Link>
                                <MoneyText value={row.total} />
                            </header>
                            <div className="flex flex-wrap items-center gap-2">
                                {showPlate && <Plate plate={row.plate} />}
                                <span className="font-medium">
                                    {row.works || '—'}
                                </span>
                            </div>
                            {row.advice && (
                                <p className="text-sm text-muted-foreground">
                                    {row.advice}
                                </p>
                            )}
                            <footer className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                                <Link
                                    href={showOrder(row.id)}
                                    className="text-muted-foreground tabular-nums hover:underline"
                                >
                                    {row.number}
                                </Link>
                                <StatusBadge
                                    type="serviceOrder"
                                    status={row.status}
                                />
                                {row.invoice && (
                                    <Link
                                        href={showInvoice(row.invoice.id)}
                                        className="inline-flex items-center gap-1.5 font-medium text-primary tabular-nums hover:underline"
                                    >
                                        <FileText className="size-4" />
                                        {row.invoice.number}
                                        <StatusBadge
                                            type="invoice"
                                            status={row.invoice.status}
                                        />
                                    </Link>
                                )}
                            </footer>
                        </article>
                    </li>
                ))}
            </ol>
        </div>
    );
}
