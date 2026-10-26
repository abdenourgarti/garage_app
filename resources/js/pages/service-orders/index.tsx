import { Head, Link, router } from '@inertiajs/react';
import { Download, ListFilter, Plus, Wrench, X } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { Plate } from '@/components/app/plate';
import { StatusBadge } from '@/components/app/status-badge';
import { FiltersSheet } from '@/components/service-orders/filters-sheet';
import type { AdvancedFilters } from '@/components/service-orders/filters-sheet';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { periodRange } from '@/lib/period';
import { show as showInvoice } from '@/routes/invoices';
import {
    create as createOrder,
    exportMethod as exportOrders,
    index as ordersIndex,
    show as showOrder,
} from '@/routes/service-orders';
import { show as showVehicle } from '@/routes/vehicles';
import type { ClientVehicleOptions } from '@/types/clients';
import type { InvoiceStatus } from '@/types/invoices';
import type { Option } from '@/types/stock';

type OrderRow = {
    id: number;
    number: string;
    date: string;
    status: 'en_cours' | 'terminee';
    client_name: string;
    plate: string;
    vehicle_id: number;
    vehicle_label: string;
    works: string;
    total: string;
    invoice: { id: number; number: string; status: InvoiceStatus } | null;
};

type Filters = {
    search: string;
    status: string | null;
    employee: number | null;
    from: string | null;
    to: string | null;
    client: string;
    wilaya: number | null;
    plate: string;
    brand: number | null;
    model: number | null;
    year_from: number | null;
    year_to: number | null;
    types: number[];
};

type Props = ClientVehicleOptions & {
    rows: Paginated<OrderRow>;
    filters: Filters;
    statuses: Option[];
    employees: { id: number; name: string }[];
    serviceTypes: { id: number; name: string }[];
};

const t = fr.serviceOrders;
const ALL = 'all';
const PERIODS = ['today', 'week', 'month'] as const;

/** Filtres de la page → paramètres d'adresse (vides retirés, types « 1,3 »). */
function toQuery(filters: Filters): Record<string, string | undefined> {
    const text = (value: string | number | null) =>
        value === null || value === '' ? undefined : String(value);

    return {
        search: text(filters.search),
        status: text(filters.status),
        employee: text(filters.employee),
        from: text(filters.from),
        to: text(filters.to),
        client: text(filters.client),
        wilaya: text(filters.wilaya),
        plate: text(filters.plate),
        brand: text(filters.brand),
        model: text(filters.model),
        year_from: text(filters.year_from),
        year_to: text(filters.year_to),
        types: filters.types.length ? filters.types.join(',') : undefined,
    };
}

const columns: Column<OrderRow>[] = [
    {
        key: 'number',
        header: t.columns.number,
        cell: (row) => (
            <span className="grid">
                <span>{row.number}</span>
                <span className="text-xs font-normal text-muted-foreground tabular-nums">
                    {formatDate(row.date)}
                </span>
            </span>
        ),
    },
    { key: 'client', header: t.columns.client, cell: (row) => row.client_name },
    {
        key: 'vehicle',
        header: t.columns.vehicle,
        cell: (row) => (
            <Link
                href={showVehicle(row.vehicle_id)}
                className="inline-flex flex-wrap items-center justify-end gap-2 md:justify-start"
            >
                <Plate plate={row.plate} />
                <span className="hidden text-sm text-muted-foreground 2xl:inline">
                    {row.vehicle_label}
                </span>
            </Link>
        ),
    },
    {
        key: 'works',
        header: t.columns.works,
        cell: (row) => (
            <span className="text-sm md:line-clamp-2 md:max-w-56">
                {row.works || '—'}
            </span>
        ),
    },
    {
        key: 'status',
        header: t.columns.status,
        cell: (row) => <StatusBadge type="serviceOrder" status={row.status} />,
    },
    {
        key: 'invoice',
        header: t.columns.invoice,
        cell: (row) =>
            row.invoice ? (
                <Link
                    href={showInvoice(row.invoice.id)}
                    className="inline-flex flex-wrap items-center justify-end gap-2 md:justify-start"
                >
                    <span className="text-sm font-medium text-primary tabular-nums hover:underline">
                        {row.invoice.number}
                    </span>
                    <StatusBadge type="invoice" status={row.invoice.status} />
                </Link>
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        key: 'total',
        header: t.columns.total,
        align: 'end',
        cell: (row) => <MoneyText value={row.total} />,
    },
];

export default function ServiceOrdersIndex({
    rows,
    filters,
    statuses,
    employees,
    serviceTypes,
    wilayas,
    brands,
    models,
}: Props) {
    const [filtering, setFiltering] = useState(false);
    const [sheetCount, setSheetCount] = useState(0);
    const query = toQuery(filters);

    const visit = (params: Record<string, string | undefined>) =>
        router.get(
            ordersIndex.url(),
            { ...query, ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const currentPeriod = PERIODS.find((period) => {
        const range = periodRange(period);

        return range.from === filters.from && range.to === filters.to;
    });

    const advancedCount = [
        filters.client,
        filters.wilaya,
        filters.plate,
        filters.brand,
        filters.year_from || filters.year_to,
        filters.types.length,
        filters.from && !currentPeriod,
    ].filter(Boolean).length;
    const anyFilter = Object.values(query).some(Boolean);

    const applyAdvanced = (advanced: AdvancedFilters) => {
        setFiltering(false);
        visit({
            client: advanced.client.trim() || undefined,
            wilaya: advanced.wilaya || undefined,
            plate: advanced.plate.trim() || undefined,
            brand: advanced.brand || undefined,
            model: advanced.model || undefined,
            year_from: advanced.year_from || undefined,
            year_to: advanced.year_to || undefined,
            from: advanced.from || undefined,
            to: advanced.to || undefined,
            types: advanced.types.length ? advanced.types.join(',') : undefined,
        });
    };

    const filterBar = (
        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
            <Select
                value={filters.status ?? ALL}
                onValueChange={(value) =>
                    visit({ status: value === ALL ? undefined : value })
                }
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-40"
                    aria-label={t.columns.status}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t.allStatuses}</SelectItem>
                    {statuses.map((status) => (
                        <SelectItem key={status.value} value={status.value}>
                            {status.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select
                value={currentPeriod ?? ALL}
                onValueChange={(value) => {
                    const range =
                        value === ALL
                            ? null
                            : periodRange(value as (typeof PERIODS)[number]);
                    visit({ from: range?.from, to: range?.to });
                }}
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-44"
                    aria-label={t.date}
                >
                    <SelectValue
                        placeholder={
                            filters.from || filters.to
                                ? `${formatDate(filters.from)} → ${formatDate(filters.to)}`
                                : undefined
                        }
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t.periods.all}</SelectItem>
                    {PERIODS.map((period) => (
                        <SelectItem key={period} value={period}>
                            {t.periods[period]}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select
                value={filters.employee ? String(filters.employee) : ALL}
                onValueChange={(value) =>
                    visit({ employee: value === ALL ? undefined : value })
                }
            >
                <SelectTrigger
                    className="h-11 w-full sm:w-48"
                    aria-label={t.mechanic}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t.allEmployees}</SelectItem>
                    {employees.map((employee) => (
                        <SelectItem
                            key={employee.id}
                            value={String(employee.id)}
                        >
                            {employee.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button
                variant={advancedCount ? 'secondary' : 'outline'}
                className="h-11"
                onClick={() => {
                    setSheetCount((count) => count + 1);
                    setFiltering(true);
                }}
            >
                <ListFilter />
                {advancedCount
                    ? `${t.filters.open} (${advancedCount})`
                    : t.filters.open}
            </Button>
            <Button asChild variant="outline" className="h-11">
                <a href={exportOrders.url({ query })} aria-label={t.export}>
                    <Download />
                    <span className="sm:hidden">{t.exportShort}</span>
                    <span className="hidden sm:inline">{t.export}</span>
                </a>
            </Button>
            {anyFilter && (
                <Button
                    variant="ghost"
                    className="col-span-2 h-11"
                    onClick={() =>
                        router.get(ordersIndex.url(), {}, { replace: true })
                    }
                >
                    <X />
                    {t.filters.clear}
                </Button>
            )}
        </div>
    );

    const newButton = (
        <Button asChild variant="highlight" className="h-11">
            <Link href={createOrder()}>
                <Plus />
                {t.newOrder}
            </Link>
        </Button>
    );

    return (
        <>
            <Head title={t.title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={t.title}
                    description={t.description}
                    actions={newButton}
                />
                <DataTable
                    key={JSON.stringify(query)}
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={(row) => showOrder.url(row.id)}
                    searchPlaceholder={t.search}
                    filters={filterBar}
                    empty={
                        anyFilter ? (
                            <EmptyState
                                icon={ListFilter}
                                title={t.noMatchTitle}
                                description={t.noMatchText}
                            />
                        ) : (
                            <EmptyState
                                icon={Wrench}
                                title={t.emptyTitle}
                                description={t.emptyText}
                                action={newButton}
                            />
                        )
                    }
                />
            </div>
            <FiltersSheet
                key={sheetCount}
                open={filtering}
                onOpenChange={setFiltering}
                initial={{
                    client: filters.client,
                    wilaya: filters.wilaya ? String(filters.wilaya) : '',
                    plate: filters.plate,
                    brand: filters.brand ? String(filters.brand) : '',
                    model: filters.model ? String(filters.model) : '',
                    year_from: filters.year_from
                        ? String(filters.year_from)
                        : '',
                    year_to: filters.year_to ? String(filters.year_to) : '',
                    from: filters.from ?? '',
                    to: filters.to ?? '',
                    types: filters.types.map(String),
                }}
                options={{ wilayas, brands, models }}
                serviceTypes={serviceTypes}
                onApply={applyAdvanced}
            />
        </>
    );
}
