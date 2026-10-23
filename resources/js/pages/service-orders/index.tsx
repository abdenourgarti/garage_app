import { Head, Link, router } from '@inertiajs/react';
import { Plus, Wrench } from 'lucide-react';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { Plate } from '@/components/app/plate';
import { StatusBadge } from '@/components/app/status-badge';
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
import {
    create as createOrder,
    index as ordersIndex,
    show as showOrder,
} from '@/routes/service-orders';
import type { Option } from '@/types/stock';

type OrderRow = {
    id: number;
    number: string;
    date: string;
    status: 'en_cours' | 'terminee';
    client_name: string;
    plate: string;
    vehicle_label: string;
    total: string;
};

type Props = {
    rows: Paginated<OrderRow>;
    filters: {
        search: string;
        status: string | null;
        employee: number | null;
        from: string | null;
        to: string | null;
    };
    statuses: Option[];
    employees: { id: number; name: string }[];
};

const t = fr.serviceOrders;
const ALL = 'all';
const PERIODS = ['today', 'week', 'month'] as const;

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
            <span className="inline-flex flex-wrap items-center justify-end gap-2 md:justify-start">
                <Plate plate={row.plate} />
                <span className="hidden text-sm text-muted-foreground xl:inline">
                    {row.vehicle_label}
                </span>
            </span>
        ),
    },
    {
        key: 'status',
        header: t.columns.status,
        cell: (row) => <StatusBadge type="serviceOrder" status={row.status} />,
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
}: Props) {
    const visit = (params: Record<string, string | undefined>) =>
        router.get(
            ordersIndex.url(),
            {
                search: filters.search || undefined,
                status: filters.status ?? undefined,
                employee: filters.employee ?? undefined,
                from: filters.from ?? undefined,
                to: filters.to ?? undefined,
                ...params,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const currentPeriod = PERIODS.find((period) => {
        const range = periodRange(period);

        return range.from === filters.from && range.to === filters.to;
    });

    const filterBar = (
        <div className="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
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
                    <SelectValue />
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
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={(row) => showOrder.url(row.id)}
                    searchPlaceholder={t.search}
                    filters={filterBar}
                    empty={
                        <EmptyState
                            icon={Wrench}
                            title={t.emptyTitle}
                            description={t.emptyText}
                            action={newButton}
                        />
                    }
                />
            </div>
        </>
    );
}
