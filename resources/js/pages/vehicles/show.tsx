import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Droplets,
    FileText,
    Pencil,
    Phone,
    Plus,
    Power,
} from 'lucide-react';
import { useState } from 'react';
import VehicleController from '@/actions/App/Http/Controllers/Clients/VehicleController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Plate } from '@/components/app/plate';
import { TransferSheet } from '@/components/clients/transfer-sheet';
import { VehicleFormSheet } from '@/components/clients/vehicle-form-sheet';
import { OrderHistory } from '@/components/service-orders/order-history';
import type { HistoryRow } from '@/components/service-orders/order-history';
import { Button } from '@/components/ui/button';
import { formatDate, formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { show as showClient } from '@/routes/clients';
import { create as createOrder } from '@/routes/service-orders';
import type { ClientVehicleOptions } from '@/types/clients';

type Person = { id: number; full_name: string; phone: string };

type Props = {
    vehicle: {
        id: number;
        plate: string;
        vin: string | null;
        brand_id: number;
        vehicle_model_id: number | null;
        year: number | null;
        energy_id: number | null;
        last_mileage: number | null;
        notes: string | null;
        is_active: boolean;
        label: string;
        brand: string;
        model: string | null;
        energy: string | null;
        owner: Person & { is_active: boolean };
        ownerships: {
            id: number;
            client: Person;
            started_at: string;
            ended_at: string | null;
        }[];
    };
    serviceOrders: HistoryRow[];
    options: ClientVehicleOptions;
};

const t = fr.vehicles;

function Info({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="font-medium">
                {value ?? (
                    <span className="font-normal text-muted-foreground">
                        {t.unknown}
                    </span>
                )}
            </dd>
        </div>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold">{title}</h2>
            {children}
        </section>
    );
}

export default function VehicleShow({
    vehicle,
    serviceOrders,
    options,
}: Props) {
    const [editing, setEditing] = useState(false);
    const [transferring, setTransferring] = useState(false);
    const [confirmToggle, setConfirmToggle] = useState(false);
    const [openCount, setOpenCount] = useState(0);

    const open = (setter: (value: boolean) => void) => {
        setOpenCount((count) => count + 1);
        setter(true);
    };

    const toggle = () =>
        router.patch(
            VehicleController.toggle.url(vehicle.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setConfirmToggle(false),
            },
        );

    return (
        <>
            <Head title={vehicle.plate} />
            <div className="space-y-8 p-4 md:p-6">
                {/* En-tête : la plaque d'abord, c'est ce que le garagiste a sous les yeux. */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center gap-3">
                            <Plate plate={vehicle.plate} size="lg" />
                            {!vehicle.is_active && (
                                <span className="rounded-full bg-muted px-2.5 py-0.5 text-sm text-muted-foreground">
                                    {fr.clients.inactive}
                                </span>
                            )}
                        </div>
                        <h1 className="text-xl font-semibold">
                            {[vehicle.label, vehicle.year]
                                .filter(Boolean)
                                .join(' ')}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2 [&>*]:flex-1 sm:[&>*]:flex-none">
                        {vehicle.is_active && (
                            <Button
                                asChild
                                variant="highlight"
                                className="h-11"
                            >
                                <Link
                                    href={createOrder({
                                        query: { vehicle: vehicle.id },
                                    })}
                                >
                                    <Plus />
                                    {fr.serviceOrders.newForVehicle}
                                </Link>
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            className="h-11"
                            onClick={() => open(setEditing)}
                        >
                            <Pencil />
                            {fr.clients.edit}
                        </Button>
                        <Button
                            variant="outline"
                            className="h-11"
                            onClick={() =>
                                vehicle.is_active
                                    ? setConfirmToggle(true)
                                    : toggle()
                            }
                        >
                            <Power />
                            {vehicle.is_active
                                ? fr.clients.deactivate
                                : fr.clients.reactivate}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-8 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div className="space-y-8">
                        <Section title={t.details}>
                            <dl className="grid grid-cols-2 gap-4 rounded-xl border bg-card p-4 sm:grid-cols-3">
                                <Info
                                    label={t.fields.brand}
                                    value={vehicle.brand}
                                />
                                <Info
                                    label={t.fields.model}
                                    value={vehicle.model}
                                />
                                <Info
                                    label={t.fields.year}
                                    value={vehicle.year}
                                />
                                <Info
                                    label={t.fields.energy}
                                    value={vehicle.energy}
                                />
                                <Info
                                    label={t.lastMileage}
                                    value={
                                        vehicle.last_mileage === null
                                            ? null
                                            : formatQuantity(
                                                  vehicle.last_mileage,
                                                  'km',
                                              )
                                    }
                                />
                                <Info
                                    label={t.fields.vin}
                                    value={vehicle.vin}
                                />
                            </dl>
                            {vehicle.notes && (
                                <p className="max-w-prose text-sm whitespace-pre-line">
                                    {vehicle.notes}
                                </p>
                            )}
                        </Section>

                        <Section title={t.logbook}>
                            <OrderHistory rows={serviceOrders} />
                        </Section>
                    </div>

                    <div className="space-y-8">
                        <Section title={t.currentOwner}>
                            <div className="space-y-3 rounded-xl border bg-card p-4">
                                <Link
                                    href={showClient(vehicle.owner.id)}
                                    className="block text-lg font-semibold hover:underline"
                                >
                                    {vehicle.owner.full_name}
                                </Link>
                                <a
                                    href={`tel:${vehicle.owner.phone.replace(/[^\d+]/g, '')}`}
                                    className="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-primary tabular-nums"
                                >
                                    <Phone className="size-4" />
                                    {vehicle.owner.phone}
                                </a>
                                <Button
                                    variant="outline"
                                    className="h-11 w-full"
                                    onClick={() => open(setTransferring)}
                                >
                                    <ArrowLeftRight />
                                    {t.changeOwner}
                                </Button>
                            </div>
                        </Section>

                        <Section title={t.nextOilChange}>
                            <div className="flex items-start gap-3 rounded-xl border border-dashed p-4 text-sm">
                                <Droplets
                                    className="mt-0.5 size-5 shrink-0 text-highlight"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p className="font-medium">
                                        {fr.common.comingSoon}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {t.nextOilChangeText}
                                    </p>
                                </div>
                            </div>
                        </Section>

                        <Section title={t.ownersHistory}>
                            <ol className="space-y-3">
                                {vehicle.ownerships.map((ownership) => (
                                    <li
                                        key={ownership.id}
                                        className="flex items-start justify-between gap-3 border-s-2 ps-3"
                                    >
                                        <div className="grid">
                                            <Link
                                                href={showClient(
                                                    ownership.client.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {ownership.client.full_name}
                                            </Link>
                                            <span className="text-sm text-muted-foreground">
                                                {t.ownerPeriod(
                                                    formatDate(
                                                        ownership.started_at,
                                                    ),
                                                    ownership.ended_at
                                                        ? formatDate(
                                                              ownership.ended_at,
                                                          )
                                                        : null,
                                                )}
                                            </span>
                                        </div>
                                        {ownership.ended_at === null && (
                                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                {t.current}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </Section>

                        <Section title={t.documents}>
                            <div className="flex items-start gap-3 rounded-xl border border-dashed p-4 text-sm">
                                <FileText
                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p className="font-medium">
                                        {fr.common.comingSoon}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {t.documentsText}
                                    </p>
                                </div>
                            </div>
                        </Section>
                    </div>
                </div>
            </div>

            <VehicleFormSheet
                key={`edit-${openCount}`}
                open={editing}
                onOpenChange={setEditing}
                options={options}
                vehicle={vehicle}
                subtitle={vehicle.plate}
            />
            <TransferSheet
                key={`transfer-${openCount}`}
                open={transferring}
                onOpenChange={setTransferring}
                vehicle={vehicle}
                options={options}
            />
            <ConfirmDialog
                open={confirmToggle}
                onOpenChange={setConfirmToggle}
                title={t.deactivateTitle}
                description={t.deactivateText}
                confirmLabel={fr.clients.deactivate}
                onConfirm={toggle}
            />
        </>
    );
}
