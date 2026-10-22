import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import VehicleController from '@/actions/App/Http/Controllers/Clients/VehicleController';
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
import { toIsoDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { clients as lookupClients } from '@/routes/lookup';
import type { ClientVehicleOptions } from '@/types/clients';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    vehicle: { id: number; plate: string; owner: { id: number } };
    options: ClientVehicleOptions;
};

async function searchClients(query: string): Promise<SelectOption[]> {
    const response = await fetch(lookupClients.url({ query: { q: query } }), {
        headers: { Accept: 'application/json' },
    });

    return response.ok ? ((await response.json()) as SelectOption[]) : [];
}

/** « Changer de propriétaire » : client existant ou créé sur place, date du jour par défaut. */
export function TransferSheet({ open, onOpenChange, vehicle, options }: Props) {
    const today = toIsoDate(new Date());
    const [owner, setOwner] = useState<SelectOption | null>(null);
    const [creating, setCreating] = useState<string | null>(null);
    const [creationCount, setCreationCount] = useState(0);
    const form = useForm({ client_id: '', date: today });

    const choose = (option: SelectOption | null) => {
        setOwner(option);
        form.setData('client_id', option?.value ?? '');
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.submit(VehicleController.transfer(vehicle.id), {
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
                            <SheetTitle>{fr.vehicles.changeOwner}</SheetTitle>
                            <SheetDescription>
                                {fr.vehicles.changeOwnerText}
                            </SheetDescription>
                        </SheetHeader>
                        <div className="grid gap-4 px-4 pb-4">
                            <FormField
                                id="transfer-client"
                                label={fr.vehicles.newOwner}
                                required
                                error={form.errors.client_id}
                            >
                                <SearchSelect
                                    id="transfer-client"
                                    value={owner}
                                    onChange={choose}
                                    loadOptions={searchClients}
                                    onCreate={(query) => {
                                        setCreationCount((count) => count + 1);
                                        setCreating(query);
                                    }}
                                    placeholder={
                                        fr.vehicles.newOwnerPlaceholder
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.client_id,
                                    )}
                                />
                            </FormField>
                            <FormField
                                id="transfer-date"
                                label={fr.vehicles.transferDate}
                                required
                                error={form.errors.date}
                            >
                                <Input
                                    id="transfer-date"
                                    type="date"
                                    className="h-11"
                                    max={today}
                                    value={form.data.date}
                                    onChange={(event) =>
                                        form.setData('date', event.target.value)
                                    }
                                    aria-invalid={Boolean(form.errors.date)}
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
                                disabled={form.processing || owner === null}
                            >
                                {form.processing && <Spinner />}
                                {fr.vehicles.confirmTransfer}
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
                allowVehicle={false}
                initialQuery={creating ?? ''}
                onCreated={({ client }) => choose(client)}
            />
        </>
    );
}
