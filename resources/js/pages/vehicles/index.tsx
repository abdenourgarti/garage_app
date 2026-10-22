import { Head, router } from '@inertiajs/react';
import { Car } from 'lucide-react';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { Plate } from '@/components/app/plate';
import { ClientsNav } from '@/components/clients/clients-nav';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { index as vehiclesIndex, show as showVehicle } from '@/routes/vehicles';

type VehicleRow = {
    id: number;
    plate: string;
    label: string;
    year: number | null;
    owner: { id: number; full_name: string; phone: string };
    last_mileage: number | null;
    is_active: boolean;
};

type Props = {
    rows: Paginated<VehicleRow>;
    filters: { search: string; brand: number | null };
    brands: { id: number; name: string }[];
};

const ALL = 'all';

const columns: Column<VehicleRow>[] = [
    {
        key: 'plate',
        header: fr.vehicles.fields.plate,
        cell: (row) => (
            <span className="inline-flex items-center gap-2">
                <Plate plate={row.plate} />
                {!row.is_active && (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground">
                        {fr.clients.inactive}
                    </span>
                )}
            </span>
        ),
    },
    {
        key: 'vehicle',
        header: fr.vehicles.vehicle,
        cell: (row) => [row.label, row.year].filter(Boolean).join(' '),
    },
    {
        key: 'owner',
        header: fr.vehicles.owner,
        cell: (row) => (
            <span className="grid">
                <span>{row.owner.full_name}</span>
                <span className="text-xs text-muted-foreground tabular-nums">
                    {row.owner.phone}
                </span>
            </span>
        ),
    },
    {
        key: 'mileage',
        header: fr.vehicles.lastMileage,
        align: 'end',
        hideOnMobile: true,
        cell: (row) =>
            row.last_mileage === null
                ? '—'
                : formatQuantity(row.last_mileage, 'km'),
    },
];

export default function VehiclesIndex({ rows, filters, brands }: Props) {
    const brandFilter = (
        <Select
            value={filters.brand ? String(filters.brand) : ALL}
            onValueChange={(value) =>
                router.get(
                    vehiclesIndex.url(),
                    {
                        search: filters.search || undefined,
                        brand: value === ALL ? undefined : value,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                )
            }
        >
            <SelectTrigger
                className="h-11 w-full sm:w-56"
                aria-label={fr.vehicles.fields.brand}
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{fr.clients.allBrands}</SelectItem>
                {brands.map((brand) => (
                    <SelectItem key={brand.id} value={String(brand.id)}>
                        {brand.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <>
            <Head title={fr.clients.tabVehicles} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={fr.clients.title}
                    description={fr.clients.description}
                />
                <ClientsNav current="vehicles" />
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={(row) => showVehicle.url(row.id)}
                    searchPlaceholder={fr.clients.searchVehicles}
                    filters={brandFilter}
                    empty={
                        <EmptyState
                            icon={Car}
                            title={fr.clients.emptyVehiclesTitle}
                            description={fr.clients.emptyVehiclesText}
                        />
                    }
                />
            </div>
        </>
    );
}
