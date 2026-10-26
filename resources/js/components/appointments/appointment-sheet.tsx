import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField } from '@/components/app/form-field';
import { SearchSelect } from '@/components/app/search-select';
import type { SelectOption } from '@/components/app/search-select';
import { QuickClientSheet } from '@/components/clients/quick-client-sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/appointments';
import {
    clientVehicles as lookupClientVehicles,
    clients as lookupClients,
} from '@/routes/lookup';
import type { Appointment } from '@/types/appointments';
import type { ClientVehicleOptions } from '@/types/clients';

const t = fr.appointments;

async function fetchOptions(url: string): Promise<SelectOption[]> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    return response.ok ? ((await response.json()) as SelectOption[]) : [];
}

/** Création rapide ou modification d'un rendez-vous Prévu. Remonté à chaque ouverture (key). */
export function AppointmentSheet({
    appointment,
    date,
    open,
    onOpenChange,
    serviceTypes,
    options,
    needVehicle = false,
}: {
    appointment: Appointment | null;
    /** Jour affiché : date proposée pour un nouveau rendez-vous. */
    date: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    serviceTypes: { id: number; name: string }[];
    options: ClientVehicleOptions;
    /** « Commencer » sans véhicule : on demande d'abord le véhicule. */
    needVehicle?: boolean;
}) {
    const [client, setClient] = useState<SelectOption | null>(
        appointment?.client ?? null,
    );
    const [vehicle, setVehicle] = useState<SelectOption | null>(
        appointment?.vehicle ?? null,
    );
    const [vehicles, setVehicles] = useState<SelectOption[]>([]);
    const [creating, setCreating] = useState<string | null>(null);
    const [creationCount, setCreationCount] = useState(0);
    const form = useForm({
        client_id: appointment?.client.value ?? '',
        vehicle_id: appointment?.vehicle?.value ?? '',
        date: appointment?.day ?? date,
        time: appointment?.time ?? '09:00',
        service_type_ids: appointment?.services.map((s) => s.id) ?? [],
        notes: appointment?.notes ?? '',
    });

    // Les véhicules du client choisi.
    useEffect(() => {
        if (!client) {
            return;
        }

        let current = true;
        void fetchOptions(lookupClientVehicles.url(Number(client.value))).then(
            (list) => current && setVehicles(list),
        );

        return () => {
            current = false;
        };
    }, [client]);

    const chooseClient = (option: SelectOption | null) => {
        setClient(option);
        setVehicles([]);
        form.setData((data) => ({
            ...data,
            client_id: option?.value ?? '',
            vehicle_id: '',
        }));
        setVehicle(null);
    };

    const chooseVehicle = (option: SelectOption | null) => {
        setVehicle(option);
        form.setData('vehicle_id', option?.value ?? '');
    };

    const toggleService = (id: number) =>
        form.setData(
            'service_type_ids',
            form.data.service_type_ids.includes(id)
                ? form.data.service_type_ids.filter((value) => value !== id)
                : [...form.data.service_type_ids, id],
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.submit(appointment ? update(appointment.id) : store(), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    <form
                        onSubmit={submit}
                        className="flex min-h-full flex-col"
                    >
                        <SheetHeader>
                            <SheetTitle>
                                {appointment ? t.editTitle : t.newTitle}
                            </SheetTitle>
                            <SheetDescription>
                                {needVehicle ? t.needVehicle : t.sheetText}
                            </SheetDescription>
                        </SheetHeader>
                        <div className="grid gap-4 px-4 pb-4">
                            <FormField
                                id="appointment-client"
                                label={t.fields.client}
                                required
                                error={form.errors.client_id}
                            >
                                <SearchSelect
                                    id="appointment-client"
                                    value={client}
                                    onChange={chooseClient}
                                    loadOptions={(query) =>
                                        fetchOptions(
                                            lookupClients.url({
                                                query: { q: query },
                                            }),
                                        )
                                    }
                                    onCreate={(query) => {
                                        setCreationCount((count) => count + 1);
                                        setCreating(query);
                                    }}
                                    placeholder={t.fields.clientPlaceholder}
                                    aria-invalid={Boolean(
                                        form.errors.client_id,
                                    )}
                                />
                            </FormField>
                            <FormField
                                id="appointment-vehicle"
                                label={t.fields.vehicle}
                                hint={
                                    needVehicle
                                        ? undefined
                                        : t.fields.vehicleHint
                                }
                                error={form.errors.vehicle_id}
                            >
                                <SearchSelect
                                    id="appointment-vehicle"
                                    value={vehicle}
                                    onChange={chooseVehicle}
                                    options={vehicles}
                                    placeholder={t.fields.vehiclePlaceholder}
                                    disabled={!client}
                                    aria-invalid={Boolean(
                                        form.errors.vehicle_id,
                                    )}
                                />
                            </FormField>
                            <div className="grid grid-cols-2 gap-3">
                                <FormField
                                    id="appointment-date"
                                    label={t.fields.date}
                                    required
                                    error={form.errors.date}
                                >
                                    <Input
                                        id="appointment-date"
                                        type="date"
                                        className="h-11"
                                        value={form.data.date}
                                        onChange={(event) =>
                                            form.setData(
                                                'date',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(form.errors.date)}
                                    />
                                </FormField>
                                <FormField
                                    id="appointment-time"
                                    label={t.fields.time}
                                    required
                                    error={form.errors.time}
                                >
                                    <Input
                                        id="appointment-time"
                                        type="time"
                                        step={900}
                                        className="h-11"
                                        value={form.data.time}
                                        onChange={(event) =>
                                            form.setData(
                                                'time',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(form.errors.time)}
                                    />
                                </FormField>
                            </div>
                            <fieldset className="grid gap-2">
                                <legend className="mb-2 text-sm font-medium">
                                    {t.fields.services}
                                </legend>
                                <div className="flex flex-wrap gap-2">
                                    {serviceTypes.map((type) => {
                                        const on =
                                            form.data.service_type_ids.includes(
                                                type.id,
                                            );

                                        return (
                                            <Button
                                                key={type.id}
                                                type="button"
                                                variant={
                                                    on ? 'default' : 'outline'
                                                }
                                                aria-pressed={on}
                                                className={cn('h-11')}
                                                onClick={() =>
                                                    toggleService(type.id)
                                                }
                                            >
                                                {on && <Check />}
                                                {type.name}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </fieldset>
                            <FormField
                                id="appointment-notes"
                                label={t.fields.notes}
                                error={form.errors.notes}
                            >
                                <Textarea
                                    id="appointment-notes"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>
                        <SheetFooter className="mt-auto flex-row gap-2 border-t">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11 flex-1"
                                onClick={() => onOpenChange(false)}
                            >
                                {fr.common.cancel}
                            </Button>
                            <Button
                                type="submit"
                                className="h-11 flex-1"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                {t.save}
                            </Button>
                        </SheetFooter>
                    </form>
                </SheetContent>
            </Sheet>

            <QuickClientSheet
                key={creationCount}
                open={creating !== null}
                onOpenChange={(isOpen) => !isOpen && setCreating(null)}
                options={options}
                initialQuery={creating ?? ''}
                onCreated={(result) => {
                    chooseClient(result.client);
                    chooseVehicle(result.vehicle);
                }}
            />
        </>
    );
}
