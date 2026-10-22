import { Head } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { StockNav } from '@/components/stock/stock-nav';
import { fr } from '@/lib/i18n/fr';
import { show as showSupplier } from '@/routes/suppliers';

type SupplierRow = {
    id: number;
    name: string;
    phone: string | null;
    debt: string;
    open_orders: number;
    is_active: boolean;
};

type Props = {
    rows: Paginated<SupplierRow>;
};

const t = fr.suppliers;

const columns: Column<SupplierRow>[] = [
    {
        key: 'name',
        header: fr.purchases.columns.supplier,
        cell: (row) => (
            <span className="inline-flex items-center gap-2">
                {row.name}
                {!row.is_active && (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground">
                        {fr.clients.inactive}
                    </span>
                )}
            </span>
        ),
    },
    {
        key: 'phone',
        header: fr.admin.fields.phone,
        cell: (row) => <span className="tabular-nums">{row.phone ?? '—'}</span>,
    },
    {
        key: 'orders',
        header: t.openOrders,
        align: 'end',
        cell: (row) => row.open_orders || '—',
        hideOnMobile: true,
    },
    {
        key: 'debt',
        header: t.debt,
        align: 'end',
        cell: (row) => (
            <MoneyText
                value={row.debt}
                className={
                    Number(row.debt) > 0 ? 'text-destructive' : undefined
                }
            />
        ),
    },
];

export default function SuppliersIndex({ rows }: Props) {
    return (
        <>
            <Head title={t.title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={fr.stock.title}
                    description={fr.stock.description}
                />
                <StockNav current="suppliers" />
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={(row) => showSupplier.url(row.id)}
                    searchPlaceholder={t.search}
                    empty={
                        <EmptyState
                            icon={Truck}
                            title={t.emptyTitle}
                            description={t.emptyText}
                        />
                    }
                />
            </div>
        </>
    );
}
