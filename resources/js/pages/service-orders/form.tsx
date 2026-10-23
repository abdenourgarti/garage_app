import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Plus,
    Save,
} from 'lucide-react';
import { useState } from 'react';
import ServiceOrderController from '@/actions/App/Http/Controllers/ServiceOrders/ServiceOrderController';
import { FormField } from '@/components/app/form-field';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { Plate } from '@/components/app/plate';
import { QuantityInput } from '@/components/app/quantity-input';
import { SearchSelect } from '@/components/app/search-select';
import { QuickClientSheet } from '@/components/clients/quick-client-sheet';
import InputError from '@/components/input-error';
import { LineCard } from '@/components/service-orders/line-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatQuantity, toIsoDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { previewLineTotal, previewSum } from '@/lib/money';
import { uid } from '@/lib/uid';
import {
    index as ordersIndex,
    show as showOrder,
} from '@/routes/service-orders';
import { serviceVehicles as lookupVehicles } from '@/routes/lookup';
import type { ClientVehicleOptions } from '@/types/clients';
import type {
    LineData,
    PartRowData,
    ServiceTypeOption,
    ServiceVehicleOption,
} from '@/types/service-orders';
import type { Option } from '@/types/stock';

type ServerPart = {
    origin: PartRowData['origin'];
    article: PartRowData['article'];
    designation: string;
    quantity: string;
    unit_price: string;
    unit_cost: string | null;
};

type Props = ClientVehicleOptions & {
    order: {
        id: number;
        number: string;
        date: string;
        mileage: number | null;
        immobilization_value: number | null;
        immobilization_unit: string | null;
        notes: string | null;
        advice: string | null;
        lines: {
            service_type_id: number;
            employee_id: number | null;
            description: string | null;
            labor_price: string;
            parts: ServerPart[];
        }[];
    } | null;
    vehicle: ServiceVehicleOption | null;
    serviceTypes: ServiceTypeOption[];
    employees: { id: number; name: string }[];
    immobilizationUnits: Option[];
};

const t = fr.serviceOrders;
const FREQUENT = 6;

async function searchVehicles(query: string): Promise<ServiceVehicleOption[]> {
    const response = await fetch(lookupVehicles.url({ query: { q: query } }), {
        headers: { Accept: 'application/json' },
    });

    return response.ok
        ? ((await response.json()) as ServiceVehicleOption[])
        : [];
}

export default function ServiceOrderForm({
    order,
    vehicle,
    serviceTypes,
    employees,
    immobilizationUnits,
    ...clientOptions
}: Props) {
    const today = toIsoDate(new Date());
    const [creatingClient, setCreatingClient] = useState<string | null>(null);
    const [sheetCount, setSheetCount] = useState(0);
    const [completing, setCompleting] = useState(false);

    const form = useForm({
        vehicle: vehicle as ServiceVehicleOption | null,
        date: order?.date ?? today,
        mileage: order?.mileage ? String(order.mileage) : '',
        immobilization_value: order?.immobilization_value
            ? String(order.immobilization_value)
            : '',
        immobilization_unit: order?.immobilization_unit ?? 'hours',
        notes: order?.notes ?? '',
        advice: order?.advice ?? '',
        lines: (order?.lines ?? []).map<LineData>((line) => ({
            key: uid(),
            service_type_id: line.service_type_id,
            employee_id: line.employee_id ? String(line.employee_id) : '',
            description: line.description ?? '',
            labor_price: line.labor_price,
            parts: line.parts.map((part) => ({
                ...part,
                key: uid(),
                isOil: false,
                oil: null,
                unit_cost: part.unit_cost ?? '',
            })),
        })),
    });

    const errors = form.errors as Record<string, string | undefined>;
    const typeById = new Map(serviceTypes.map((type) => [type.id, type]));
    const selectedTypes = form.data.lines
        .map((line) => typeById.get(line.service_type_id))
        .filter(Boolean) as ServiceTypeOption[];
    const mileageRequiredBy = selectedTypes
        .filter((type) => type.requires_mileage)
        .map((type) => type.name);
    const lastMileage = form.data.vehicle?.last_mileage ?? null;
    const mileageLower =
        lastMileage !== null &&
        form.data.mileage !== '' &&
        Number(form.data.mileage) < lastMileage;

    const laborTotal = previewSum(
        form.data.lines.map((line) => line.labor_price),
    );
    const partsTotal = previewSum(
        form.data.lines.flatMap((line) =>
            line.parts
                .filter((part) => part.origin !== 'client')
                .map((part) =>
                    previewLineTotal(part.quantity, part.unit_price),
                ),
        ),
    );

    const addLine = (type: ServiceTypeOption) => {
        const previous = form.data.lines.at(-1)?.employee_id;
        const onlyMechanic =
            employees.length === 1 ? String(employees[0].id) : '';
        // Une vidange appelle presque toujours une huile : la ligne « Huile » est ouverte d'office.
        const parts: PartRowData[] = /vidange/i.test(type.name)
            ? [
                  {
                      key: uid(),
                      origin: 'stock',
                      isOil: true,
                      oil: null,
                      article: null,
                      designation: '',
                      quantity: '1',
                      unit_price: '',
                      unit_cost: '',
                  },
              ]
            : [];

        form.setData('lines', [
            ...form.data.lines,
            {
                key: uid(),
                service_type_id: type.id,
                employee_id: previous || onlyMechanic,
                description: '',
                labor_price: type.default_labor_price,
                parts,
            },
        ]);
    };

    const setLine = (key: number, patch: Partial<LineData>) =>
        form.setData(
            'lines',
            form.data.lines.map((line) =>
                line.key === key ? { ...line, ...patch } : line,
            ),
        );

    const submit = (complete: boolean) => {
        setCompleting(complete);
        form.transform(
            (data) =>
                ({
                    vehicle_id: data.vehicle?.value ?? '',
                    date: data.date,
                    mileage: data.mileage || null,
                    immobilization_value: data.immobilization_value || null,
                    immobilization_unit: data.immobilization_value
                        ? data.immobilization_unit
                        : null,
                    notes: data.notes || null,
                    advice: data.advice || null,
                    complete,
                    lines: data.lines.map((line) => ({
                        service_type_id: line.service_type_id,
                        employee_id: line.employee_id || null,
                        description: line.description || null,
                        labor_price: line.labor_price || '0',
                        // Une ligne « Huile » sans format choisi n'est pas envoyée.
                        parts: line.parts
                            .filter((part) => !(part.isOil && !part.article))
                            .map((part) => ({
                                origin: part.origin,
                                article_id: part.article?.value ?? null,
                                designation: part.designation || null,
                                quantity: part.quantity,
                                unit_price:
                                    part.origin === 'client'
                                        ? null
                                        : part.unit_price || null,
                                unit_cost:
                                    part.origin === 'external'
                                        ? part.unit_cost || null
                                        : null,
                            })),
                    })),
                }) as never,
        );

        if (order) {
            form.put(ServiceOrderController.update.url(order.id), {
                preserveScroll: true,
            });
        } else {
            form.post(ServiceOrderController.store.url(), {
                preserveScroll: true,
            });
        }
    };

    const frequentTypes = serviceTypes.slice(0, FREQUENT);
    const otherTypes = serviceTypes
        .slice(FREQUENT)
        .map((type) => ({ value: String(type.id), label: type.name }));

    const recap = (
        <div className="grid gap-3">
            <dl className="grid gap-1 text-sm">
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">{t.laborTotal}</dt>
                    <dd>
                        <MoneyText value={laborTotal} className="font-medium" />
                    </dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">{t.partsTotal}</dt>
                    <dd>
                        <MoneyText value={partsTotal} className="font-medium" />
                    </dd>
                </div>
                <div className="flex items-baseline justify-between gap-2 border-t pt-2">
                    <dt className="font-semibold">{t.total}</dt>
                    <dd>
                        <MoneyText
                            value={laborTotal + partsTotal}
                            className="text-2xl"
                        />
                    </dd>
                </div>
            </dl>
            <InputError message={errors.lines ?? errors.status} />
            <div className="grid grid-cols-2 gap-2 lg:grid-cols-1">
                <Button
                    type="button"
                    variant="outline"
                    className="h-11"
                    disabled={form.processing}
                    onClick={() => submit(false)}
                >
                    {form.processing && !completing ? <Spinner /> : <Save />}
                    {t.save}
                </Button>
                <Button
                    type="button"
                    className="h-11"
                    disabled={form.processing}
                    onClick={() => submit(true)}
                >
                    {form.processing && completing ? (
                        <Spinner />
                    ) : (
                        <CheckCircle2 />
                    )}
                    <span className="lg:hidden">{t.completeShort}</span>
                    <span className="hidden lg:inline">{t.complete}</span>
                </Button>
            </div>
        </div>
    );

    return (
        <>
            <Head
                title={order ? `${t.editOrder} ${order.number}` : t.newOrder}
            />
            <form
                onSubmit={(event) => event.preventDefault()}
                className="space-y-6 p-4 pb-48 md:p-6 lg:pb-6"
            >
                <PageHeader
                    title={
                        order ? `${t.editOrder} ${order.number}` : t.newOrder
                    }
                    actions={
                        <Button asChild variant="outline" className="h-11">
                            <Link
                                href={
                                    order ? showOrder(order.id) : ordersIndex()
                                }
                            >
                                <ArrowLeft />
                                {fr.common.cancel}
                            </Link>
                        </Button>
                    }
                />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="grid gap-6">
                        {/* 1. Client et véhicule */}
                        <section className="grid gap-3 rounded-xl border bg-card p-4">
                            <h2 className="font-semibold">
                                {t.sections.vehicle}
                            </h2>
                            {form.data.vehicle ? (
                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-background p-3">
                                    <div className="grid gap-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Plate
                                                plate={form.data.vehicle.label}
                                            />
                                            <span className="font-medium">
                                                {
                                                    form.data.vehicle
                                                        .vehicle_label
                                                }
                                            </span>
                                        </div>
                                        {form.data.vehicle.client && (
                                            <span className="text-sm">
                                                {
                                                    form.data.vehicle.client
                                                        .full_name
                                                }
                                                ,{' '}
                                                <span className="tabular-nums">
                                                    {
                                                        form.data.vehicle.client
                                                            .phone
                                                    }
                                                </span>
                                            </span>
                                        )}
                                        <span className="text-sm text-muted-foreground">
                                            {lastMileage !== null
                                                ? t.lastMileage(
                                                      formatQuantity(
                                                          lastMileage,
                                                          'km',
                                                      ),
                                                  )
                                                : t.noMileage}
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-11"
                                        onClick={() =>
                                            form.setData('vehicle', null)
                                        }
                                    >
                                        {t.changeVehicle}
                                    </Button>
                                </div>
                            ) : (
                                <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <SearchSelect
                                        id="order-vehicle"
                                        value={null}
                                        onChange={(option) =>
                                            form.setData(
                                                'vehicle',
                                                option as ServiceVehicleOption | null,
                                            )
                                        }
                                        loadOptions={searchVehicles}
                                        onCreate={(query) => {
                                            setSheetCount((count) => count + 1);
                                            setCreatingClient(query);
                                        }}
                                        placeholder={t.vehicleSearch}
                                        aria-invalid={Boolean(
                                            errors.vehicle_id,
                                        )}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-11"
                                        onClick={() => {
                                            setSheetCount((count) => count + 1);
                                            setCreatingClient('');
                                        }}
                                    >
                                        <Plus />
                                        {t.newClient}
                                    </Button>
                                </div>
                            )}
                            <InputError message={errors.vehicle_id} />
                        </section>

                        {/* 2. Kilométrage, date, immobilisation */}
                        <section className="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-3">
                            <h2 className="font-semibold sm:col-span-3">
                                {t.sections.visit}
                            </h2>
                            <FormField
                                id="order-mileage"
                                label={t.mileage}
                                required={mileageRequiredBy.length > 0}
                                error={errors.mileage}
                            >
                                <QuantityInput
                                    id="order-mileage"
                                    unit="km"
                                    value={form.data.mileage}
                                    onValueChange={(value) =>
                                        form.setData('mileage', value)
                                    }
                                    aria-invalid={Boolean(errors.mileage)}
                                />
                                {mileageLower && lastMileage !== null && (
                                    <p className="flex items-start gap-1.5 text-sm font-medium text-warning">
                                        <AlertTriangle
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {t.mileageLower(
                                            formatQuantity(lastMileage, 'km'),
                                        )}
                                    </p>
                                )}
                                {mileageRequiredBy.length > 0 &&
                                    !errors.mileage && (
                                        <p className="text-xs text-muted-foreground">
                                            {t.mileageRequired}
                                            {mileageRequiredBy.join(', ')}
                                        </p>
                                    )}
                            </FormField>
                            <FormField
                                id="order-date"
                                label={t.date}
                                required
                                error={errors.date}
                            >
                                <Input
                                    id="order-date"
                                    type="date"
                                    className="h-11"
                                    max={today}
                                    value={form.data.date}
                                    onChange={(event) =>
                                        form.setData('date', event.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="order-immobilization"
                                label={t.immobilization}
                                error={errors.immobilization_value}
                            >
                                <div className="grid grid-cols-[5rem_1fr] gap-2">
                                    <Input
                                        id="order-immobilization"
                                        inputMode="numeric"
                                        className="h-11 text-end tabular-nums"
                                        value={form.data.immobilization_value}
                                        onChange={(event) =>
                                            form.setData(
                                                'immobilization_value',
                                                event.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                    />
                                    <Select
                                        value={form.data.immobilization_unit}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'immobilization_unit',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            className="h-11 w-full"
                                            aria-label={t.immobilization}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {immobilizationUnits.map((unit) => (
                                                <SelectItem
                                                    key={unit.value}
                                                    value={unit.value}
                                                >
                                                    {unit.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </FormField>
                        </section>

                        {/* 3. Travaux */}
                        <section className="grid gap-3">
                            <h2 className="font-semibold">
                                {t.sections.works}
                            </h2>
                            <div className="flex flex-wrap gap-2">
                                {frequentTypes.map((type) => (
                                    <Button
                                        key={type.id}
                                        type="button"
                                        variant="secondary"
                                        className="h-11"
                                        onClick={() => addLine(type)}
                                    >
                                        <Plus />
                                        {type.name}
                                    </Button>
                                ))}
                                {otherTypes.length > 0 && (
                                    <div className="w-full sm:w-64">
                                        <SearchSelect
                                            value={null}
                                            options={otherTypes}
                                            onChange={(option) => {
                                                const type = option
                                                    ? typeById.get(
                                                          Number(option.value),
                                                      )
                                                    : undefined;

                                                if (type) {
                                                    addLine(type);
                                                }
                                            }}
                                            placeholder={t.otherWork}
                                        />
                                    </div>
                                )}
                            </div>
                            {form.data.lines.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    {t.noLine}
                                </p>
                            )}
                            {form.data.lines.map((line, index) => (
                                <LineCard
                                    key={line.key}
                                    line={line}
                                    index={index}
                                    typeName={
                                        typeById.get(line.service_type_id)
                                            ?.name ?? ''
                                    }
                                    employees={employees}
                                    oilCapacity={
                                        form.data.vehicle?.oil_capacity_l ??
                                        null
                                    }
                                    onChange={(patch) =>
                                        setLine(line.key, patch)
                                    }
                                    onRemove={() =>
                                        form.setData(
                                            'lines',
                                            form.data.lines.filter(
                                                (item) => item.key !== line.key,
                                            ),
                                        )
                                    }
                                    errors={errors}
                                />
                            ))}
                        </section>

                        {/* 4. Conseil */}
                        <section className="grid gap-4 rounded-xl border bg-card p-4">
                            <FormField
                                id="order-advice"
                                label={t.sections.advice}
                                error={errors.advice}
                            >
                                <Textarea
                                    id="order-advice"
                                    placeholder={t.advicePlaceholder}
                                    value={form.data.advice}
                                    onChange={(event) =>
                                        form.setData(
                                            'advice',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="order-notes"
                                label={t.notes}
                                error={errors.notes}
                            >
                                <Textarea
                                    id="order-notes"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </section>
                    </div>

                    {/* Récapitulatif : colonne collante sur PC, barre collante au-dessus de la barre basse sur mobile. */}
                    <aside className="sticky top-20 hidden rounded-xl border bg-card p-4 lg:block">
                        {recap}
                    </aside>
                </div>

                <div className="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 border-t bg-background/95 p-3 backdrop-blur md:bottom-0 lg:hidden">
                    {recap}
                </div>
            </form>

            <QuickClientSheet
                key={sheetCount}
                open={creatingClient !== null}
                onOpenChange={(open) => !open && setCreatingClient(null)}
                options={clientOptions}
                withVehicle
                initialQuery={creatingClient ?? ''}
                onCreated={({ client, vehicle: created }) => {
                    if (created) {
                        form.setData('vehicle', {
                            ...created,
                            vehicle_label: created.description,
                            client: {
                                id: Number(client.value),
                                full_name: client.label,
                                phone: client.description ?? '',
                            },
                            last_mileage: null,
                            oil_capacity_l: null,
                        });
                    }
                }}
            />
        </>
    );
}
