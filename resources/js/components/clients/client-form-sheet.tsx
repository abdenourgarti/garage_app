import { useForm } from '@inertiajs/react';
import ClientController from '@/actions/App/Http/Controllers/Clients/ClientController';
import { ClientFields } from '@/components/clients/client-fields';
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
import { nullable } from '@/types/clients';
import type { ClientFormData, ClientVehicleOptions } from '@/types/clients';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    client: { id: number; full_name: string } & Record<
        keyof ClientFormData,
        string | number | null
    >;
    options: ClientVehicleOptions;
};

/** Modification d'un client (la page le remonte à chaque ouverture via key). */
export function ClientFormSheet({
    open,
    onOpenChange,
    client,
    options,
}: Props) {
    const form = useForm<ClientFormData>({
        last_name: String(client.last_name ?? ''),
        first_name: String(client.first_name ?? ''),
        phone: String(client.phone ?? ''),
        wilaya_id: client.wilaya_id ? String(client.wilaya_id) : '',
        notes: String(client.notes ?? ''),
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => nullable(data) as ClientFormData);
        form.submit(ClientController.update(client.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>{fr.clients.editClient}</SheetTitle>
                        <SheetDescription>{client.full_name}</SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-4">
                        <ClientFields
                            data={form.data}
                            setData={(field, value) =>
                                form.setData(field, value)
                            }
                            errors={form.errors}
                            options={options}
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
