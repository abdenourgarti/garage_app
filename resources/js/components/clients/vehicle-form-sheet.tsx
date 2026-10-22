import { useForm } from '@inertiajs/react';
import VehicleController from '@/actions/App/Http/Controllers/Clients/VehicleController';
import { VehicleFields } from '@/components/clients/vehicle-fields';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { fr } from '@/lib/i18n/fr';
import { emptyVehicle, nullable } from '@/types/clients';
import type { ClientVehicleOptions, VehicleFormData } from '@/types/clients';

export type EditableVehicle = {
    id: number;
    brand: string;
    model: string | null;
} & Record<keyof VehicleFormData, string | number | null>;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: ClientVehicleOptions;
    /** Ajout pour ce client… */
    clientId?: number;
    /** …ou modification de ce véhicule. */
    vehicle?: EditableVehicle;
    subtitle: string;
};

function initialData(vehicle?: EditableVehicle): VehicleFormData {
    if (!vehicle) {
        return emptyVehicle;
    }

    return Object.fromEntries(
        Object.keys(emptyVehicle).map((key) => {
            const value = vehicle[key as keyof VehicleFormData];

            return [
                key,
                value === null || value === undefined ? '' : String(value),
            ];
        }),
    ) as VehicleFormData;
}

/** Ajout d'un véhicule à un client, ou modification (la page le remonte à chaque ouverture via key). */
export function VehicleFormSheet({
    open,
    onOpenChange,
    options,
    clientId,
    vehicle,
    subtitle,
}: Props) {
    const form = useForm<VehicleFormData>(initialData(vehicle));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => nullable(data) as VehicleFormData);
        form.submit(
            vehicle
                ? VehicleController.update(vehicle.id)
                : VehicleController.store(clientId ?? 0),
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>
                            {vehicle
                                ? fr.vehicles.editVehicle
                                : fr.vehicles.addVehicle}
                        </SheetTitle>
                        <SheetDescription>{subtitle}</SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-4">
                        <VehicleFields
                            data={form.data}
                            setData={(field, value) =>
                                form.setData(field, value)
                            }
                            errors={form.errors}
                            options={options}
                            current={
                                vehicle
                                    ? {
                                          brand: vehicle.brand,
                                          model: vehicle.model,
                                      }
                                    : undefined
                            }
                        />
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
                            {fr.common.save}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
