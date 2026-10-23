import { Link } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { Plate } from '@/components/app/plate';
import { StatusBadge } from '@/components/app/status-badge';
import { formatDate, formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { show as showOrder } from '@/routes/service-orders';

export type HistoryRow = {
    id: number;
    number: string;
    date: string;
    mileage: number | null;
    status: 'en_cours' | 'terminee';
    plate: string;
    works: string;
    advice: string | null;
    total: string;
};

/**
 * Carnet d'entretien (fiche véhicule) ou prestations d'un client : une carte par passage, du plus récent au plus ancien.
 */
export function OrderHistory({
    rows,
    showPlate = false,
}: {
    rows: HistoryRow[];
    showPlate?: boolean;
}) {
    if (rows.length === 0) {
        return (
            <EmptyState icon={Wrench} title={fr.serviceOrders.logbookEmpty} />
        );
    }

    return (
        <ol className="grid gap-2">
            {rows.map((row) => (
                <li key={row.id}>
                    <Link
                        href={showOrder(row.id)}
                        className="grid gap-1.5 rounded-xl border bg-card p-3 hover:border-primary/40 sm:grid-cols-[8rem_minmax(0,1fr)_auto] sm:items-center sm:gap-4"
                    >
                        <span className="grid">
                            <span className="font-semibold tabular-nums">
                                {formatDate(row.date)}
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {row.mileage === null
                                    ? row.number
                                    : `${formatQuantity(row.mileage, 'km')}, ${row.number}`}
                            </span>
                        </span>
                        <span className="grid min-w-0 gap-0.5">
                            <span className="flex flex-wrap items-center gap-2">
                                {showPlate && <Plate plate={row.plate} />}
                                <span className="font-medium">
                                    {row.works || '—'}
                                </span>
                            </span>
                            {row.advice && (
                                <span className="truncate text-sm text-muted-foreground">
                                    {row.advice}
                                </span>
                            )}
                        </span>
                        <span className="flex items-center justify-between gap-3 sm:justify-end">
                            <StatusBadge
                                type="serviceOrder"
                                status={row.status}
                            />
                            <MoneyText value={row.total} />
                        </span>
                    </Link>
                </li>
            ))}
        </ol>
    );
}
