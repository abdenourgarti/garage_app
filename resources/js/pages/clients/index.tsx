import { Head, router } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { Plate } from '@/components/app/plate';
import { ClientsNav } from '@/components/clients/clients-nav';
import { QuickClientSheet } from '@/components/clients/quick-client-sheet';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { fr } from '@/lib/i18n/fr';
import { index as clientsIndex, show as showClient } from '@/routes/clients';
import type { ClientVehicleOptions } from '@/types/clients';

type ClientRow = {
    id: number;
    full_name: string;
    phone: string;
    wilaya: string | null;
    plates: string[];
    is_active: boolean;
};

type Props = {
    rows: Paginated<ClientRow>;
    filters: { search: string; wilaya: number | null };
    options: ClientVehicleOptions;
};

const ALL = 'all';

const columns: Column<ClientRow>[] = [
    {
        key: 'name',
        header: fr.clients.name,
        cell: (row) => (
            <span className="inline-flex items-center gap-2">
                {row.full_name}
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
        header: fr.clients.fields.phone,
        cell: (row) => <span className="tabular-nums">{row.phone}</span>,
    },
    {
        key: 'wilaya',
        header: fr.clients.fields.wilaya,
        cell: (row) => row.wilaya ?? '—',
        hideOnMobile: true,
    },
    {
        key: 'vehicles',
        header: fr.clients.vehicles,
        cell: (row) =>
            row.plates.length === 0 ? (
                <span className="text-muted-foreground">
                    {fr.clients.vehicleCount(0)}
                </span>
            ) : (
                <span className="inline-flex flex-wrap justify-end gap-1 md:justify-start">
                    {row.plates.map((plate) => (
                        <Plate key={plate} plate={plate} />
                    ))}
                </span>
            ),
    },
];

export default function ClientsIndex({ rows, filters, options }: Props) {
    const [creating, setCreating] = useState(false);
    const [openCount, setOpenCount] = useState(0);

    const wilayaFilter = (
        <Select
            value={filters.wilaya ? String(filters.wilaya) : ALL}
            onValueChange={(value) =>
                router.get(
                    clientsIndex.url(),
                    {
                        search: filters.search || undefined,
                        wilaya: value === ALL ? undefined : value,
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
                className="h-11 w-full sm:w-60"
                aria-label={fr.clients.fields.wilaya}
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{fr.clients.allWilayas}</SelectItem>
                {options.wilayas.map((wilaya) => (
                    <SelectItem key={wilaya.id} value={String(wilaya.id)}>
                        {wilaya.code} {wilaya.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    const openCreate = () => {
        setOpenCount((count) => count + 1);
        setCreating(true);
    };

    return (
        <>
            <Head title={fr.clients.tabClients} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={fr.clients.title}
                    description={fr.clients.description}
                    actions={
                        <Button className="h-11" onClick={openCreate}>
                            <Plus />
                            {fr.clients.newClient}
                        </Button>
                    }
                />
                <ClientsNav current="clients" />
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={(row) => showClient.url(row.id)}
                    searchPlaceholder={fr.clients.searchClients}
                    filters={wilayaFilter}
                    empty={
                        <EmptyState
                            icon={Users}
                            title={fr.clients.emptyTitle}
                            description={fr.clients.emptyText}
                            action={
                                <Button className="h-11" onClick={openCreate}>
                                    <Plus />
                                    {fr.clients.newClient}
                                </Button>
                            }
                        />
                    }
                />
            </div>

            <QuickClientSheet
                key={openCount}
                open={creating}
                onOpenChange={setCreating}
                options={options}
                onCreated={({ client }) =>
                    router.visit(showClient.url(Number(client.value)))
                }
            />
        </>
    );
}
