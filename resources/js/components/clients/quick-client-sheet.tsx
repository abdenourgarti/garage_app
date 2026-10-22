import { useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import ClientController from '@/actions/App/Http/Controllers/Clients/ClientController';
import type { SelectOption } from '@/components/app/search-select';
import { ClientFields } from '@/components/clients/client-fields';
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
import { Switch } from '@/components/ui/switch';
import { fr } from '@/lib/i18n/fr';
import { emptyClient, emptyVehicle, nullable } from '@/types/clients';
import type {
    ClientFormData,
    ClientVehicleOptions,
    VehicleFormData,
} from '@/types/clients';

export type QuickClientResult = {
    client: SelectOption;
    vehicle: SelectOption | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: ClientVehicleOptions;
    /** Proposer (et cocher par défaut) le premier véhicule : l'écran prestation en a besoin. */
    withVehicle?: boolean;
    /** Le véhicule peut-il être retiré du formulaire ? (non pour « changer de propriétaire »). */
    allowVehicle?: boolean;
    /** Texte saisi dans un sélecteur avant de cliquer « + Ajouter » : pré-rempli dans le bon champ. */
    initialQuery?: string;
    onCreated: (result: QuickClientResult) => void;
};

type QuickForm = ClientFormData & VehicleFormData & { with_vehicle: boolean };

function initialData(query: string, withVehicle: boolean): QuickForm {
    // Un texte avec des chiffres est un téléphone, sinon un nom.
    const isPhone = /\d/.test(query);

    return {
        ...emptyVehicle,
        ...emptyClient,
        phone: isPhone ? query : '',
        last_name: isPhone ? '' : query,
        with_vehicle: withVehicle,
    };
}

/**
 * Création rapide d'un client (et de son premier véhicule) sans quitter l'écran :
 * requête JSON, le parent reçoit les deux éléments sous forme d'options de sélecteur.
 * Le parent le remonte à chaque ouverture (key) pour repartir d'un formulaire vide.
 */
export function QuickClientSheet({
    open,
    onOpenChange,
    options,
    withVehicle = false,
    allowVehicle = true,
    initialQuery = '',
    onCreated,
}: Props) {
    const http = useHttp<QuickForm, QuickClientResult>(
        initialData(initialQuery, withVehicle && allowVehicle),
    );

    const setField = (field: keyof QuickForm, value: string | boolean) =>
        http.setData(field, value as never);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        http.transform((data) => nullable(data) as QuickForm);
        void http
            .submit(ClientController.quickStore())
            .then((result) => {
                toast.success(
                    result.vehicle
                        ? fr.clients.quick.createdWithVehicle
                        : fr.clients.quick.created,
                );
                onCreated(result);
                onOpenChange(false);
            })
            .catch(() => {
                // Erreurs de validation : affichées sous les champs (http.errors).
            });
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>{fr.clients.quick.title}</SheetTitle>
                        <SheetDescription>
                            {fr.clients.quick.description}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="grid gap-6 px-4 pb-4">
                        <ClientFields
                            data={http.data}
                            setData={setField}
                            errors={http.errors}
                            options={options}
                        />

                        {allowVehicle && (
                            <section className="grid gap-4 border-t pt-4">
                                <div className="flex min-h-11 items-center justify-between gap-4">
                                    <label
                                        htmlFor="with-vehicle"
                                        className="text-sm font-medium"
                                    >
                                        {fr.clients.quick.withVehicle}
                                    </label>
                                    <Switch
                                        id="with-vehicle"
                                        checked={http.data.with_vehicle}
                                        onCheckedChange={(checked) =>
                                            setField('with_vehicle', checked)
                                        }
                                    />
                                </div>
                                {http.data.with_vehicle && (
                                    <VehicleFields
                                        data={http.data}
                                        setData={setField}
                                        errors={http.errors}
                                        options={options}
                                        showNotes={false}
                                    />
                                )}
                            </section>
                        )}
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
                            disabled={http.processing}
                        >
                            {http.processing && <Spinner />}
                            {fr.common.save}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
