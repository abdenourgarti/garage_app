import { Head, Link, router } from '@inertiajs/react';
import { Car, MapPin, Pencil, Phone, Plus, Power } from 'lucide-react';
import { useState } from 'react';
import ClientController from '@/actions/App/Http/Controllers/Clients/ClientController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { Plate } from '@/components/app/plate';
import { ClientFormSheet } from '@/components/clients/client-form-sheet';
import { VehicleFormSheet } from '@/components/clients/vehicle-form-sheet';
import { InvoiceList } from '@/components/invoices/invoice-list';
import { OrderHistory } from '@/components/service-orders/order-history';
import type { HistoryRow } from '@/components/service-orders/order-history';
import { Button } from '@/components/ui/button';
import { formatDate, formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { show as showVehicle } from '@/routes/vehicles';
import type { ClientVehicleOptions } from '@/types/clients';
import type { InvoiceRow } from '@/types/invoices';

type Props = {
    client: {
        id: number;
        first_name: string | null;
        last_name: string;
        full_name: string;
        phone: string;
        wilaya_id: number | null;
        wilaya: string | null;
        notes: string | null;
        is_active: boolean;
        created_at: string | null;
    };
    vehicles: {
        id: number;
        plate: string;
        label: string;
        year: number | null;
        last_mileage: number | null;
        is_active: boolean;
    }[];
    serviceOrders: HistoryRow[];
    invoices: InvoiceRow[];
    credit: string;
    options: ClientVehicleOptions;
};

type Tab = 'vehicles' | 'serviceOrders' | 'invoices' | 'credit';

const t = fr.clients;

export default function ClientShow({
    client,
    vehicles,
    serviceOrders,
    invoices,
    credit,
    options,
}: Props) {
    const [tab, setTab] = useState<Tab>('vehicles');
    const [editing, setEditing] = useState(false);
    const [addingVehicle, setAddingVehicle] = useState(false);
    const [confirmToggle, setConfirmToggle] = useState(false);
    const [openCount, setOpenCount] = useState(0);

    const open = (setter: (value: boolean) => void) => {
        setOpenCount((count) => count + 1);
        setter(true);
    };

    const toggle = () =>
        router.patch(
            ClientController.toggle.url(client.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setConfirmToggle(false),
            },
        );

    const tabs: { key: Tab; label: string }[] = [
        { key: 'vehicles', label: `${t.tabs.vehicles} (${vehicles.length})` },
        {
            key: 'serviceOrders',
            label: `${t.tabs.serviceOrders} (${serviceOrders.length})`,
        },
        { key: 'invoices', label: `${t.tabs.invoices} (${invoices.length})` },
        { key: 'credit', label: t.tabs.credit },
    ];

    return (
        <>
            <Head title={client.full_name} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-2">
                        <h1 className="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
                            {client.full_name}
                            {!client.is_active && (
                                <span className="rounded-full bg-muted px-2.5 py-0.5 text-sm font-normal text-muted-foreground">
                                    {t.inactive}
                                </span>
                            )}
                        </h1>
                        <div className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground">
                            <a
                                href={`tel:${client.phone.replace(/[^\d+]/g, '')}`}
                                className="inline-flex min-h-11 items-center gap-1.5 font-medium text-primary tabular-nums sm:min-h-0"
                                aria-label={`${t.call} ${client.phone}`}
                            >
                                <Phone className="size-4" />
                                {client.phone}
                            </a>
                            {client.wilaya && (
                                <span className="inline-flex items-center gap-1.5">
                                    <MapPin className="size-4" />
                                    {client.wilaya}
                                </span>
                            )}
                            {client.created_at && (
                                <span>
                                    {t.since(formatDate(client.created_at))}
                                </span>
                            )}
                        </div>
                        {client.notes && (
                            <p className="max-w-prose text-sm whitespace-pre-line">
                                {client.notes}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">
                        <Button
                            variant="outline"
                            className="h-11"
                            onClick={() => open(setEditing)}
                        >
                            <Pencil />
                            {t.edit}
                        </Button>
                        <Button
                            variant="outline"
                            className="h-11"
                            onClick={() =>
                                client.is_active
                                    ? setConfirmToggle(true)
                                    : toggle()
                            }
                        >
                            <Power />
                            {client.is_active ? t.deactivate : t.reactivate}
                        </Button>
                    </div>
                </div>

                <div
                    role="tablist"
                    aria-label={client.full_name}
                    className="flex gap-1 overflow-x-auto border-b"
                >
                    {tabs.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            role="tab"
                            aria-selected={tab === item.key}
                            onClick={() => setTab(item.key)}
                            className={cn(
                                '-mb-px flex min-h-11 shrink-0 items-center border-b-2 border-transparent px-3 text-sm font-medium text-muted-foreground hover:text-foreground',
                                tab === item.key &&
                                    'border-primary text-foreground',
                            )}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                <div role="tabpanel">
                    {tab === 'vehicles' && (
                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button
                                    className="h-11"
                                    onClick={() => open(setAddingVehicle)}
                                >
                                    <Plus />
                                    {fr.vehicles.addVehicle}
                                </Button>
                            </div>
                            {vehicles.length === 0 ? (
                                <EmptyState
                                    icon={Car}
                                    title={t.vehicleCount(0)}
                                    action={
                                        <Button
                                            className="h-11"
                                            onClick={() =>
                                                open(setAddingVehicle)
                                            }
                                        >
                                            <Plus />
                                            {fr.vehicles.addVehicle}
                                        </Button>
                                    }
                                />
                            ) : (
                                <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    {vehicles.map((vehicle) => (
                                        <li key={vehicle.id}>
                                            <Link
                                                href={showVehicle(vehicle.id)}
                                                className={cn(
                                                    'flex h-full flex-col gap-3 rounded-xl border bg-card p-4 hover:border-primary/40 active:bg-accent',
                                                    !vehicle.is_active &&
                                                        'opacity-60',
                                                )}
                                            >
                                                <Plate
                                                    plate={vehicle.plate}
                                                    className="self-start text-base"
                                                />
                                                <span className="font-medium">
                                                    {[
                                                        vehicle.label,
                                                        vehicle.year,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' ')}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {fr.vehicles.lastMileage} :{' '}
                                                    {vehicle.last_mileage ===
                                                    null
                                                        ? fr.vehicles.unknown
                                                        : formatQuantity(
                                                              vehicle.last_mileage,
                                                              'km',
                                                          )}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                    {tab === 'serviceOrders' && (
                        <OrderHistory rows={serviceOrders} showPlate />
                    )}
                    {tab === 'invoices' && (
                        <InvoiceList
                            rows={invoices}
                            emptyTitle={t.invoicesEmpty}
                        />
                    )}
                    {tab === 'credit' && (
                        <div className="space-y-4">
                            <div className="max-w-sm rounded-xl border bg-card p-4">
                                <p className="text-sm text-muted-foreground">
                                    {t.creditTitle}
                                </p>
                                <MoneyText
                                    value={credit}
                                    className={cn(
                                        'mt-1 block text-2xl',
                                        Number(credit) > 0 &&
                                            'text-destructive',
                                    )}
                                />
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {t.creditText}
                                </p>
                            </div>
                            <InvoiceList
                                rows={invoices.filter(
                                    (invoice) =>
                                        invoice.status === 'impayee' ||
                                        invoice.status === 'partielle',
                                )}
                                emptyTitle={fr.invoices.credits.emptyTitle}
                            />
                        </div>
                    )}
                </div>
            </div>

            <ClientFormSheet
                key={`client-${openCount}`}
                open={editing}
                onOpenChange={setEditing}
                client={client}
                options={options}
            />
            <VehicleFormSheet
                key={`vehicle-${openCount}`}
                open={addingVehicle}
                onOpenChange={setAddingVehicle}
                options={options}
                clientId={client.id}
                subtitle={client.full_name}
            />
            <ConfirmDialog
                open={confirmToggle}
                onOpenChange={setConfirmToggle}
                title={t.deactivateClientTitle}
                description={t.deactivateClientText}
                confirmLabel={t.deactivate}
                onConfirm={toggle}
            />
        </>
    );
}
