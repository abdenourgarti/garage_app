import { FormField } from '@/components/app/form-field';
import { SearchSelect } from '@/components/app/search-select';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { fr } from '@/lib/i18n/fr';
import type { ClientFormData, ClientVehicleOptions } from '@/types/clients';

type Props = {
    data: ClientFormData;
    setData: (field: keyof ClientFormData, value: string) => void;
    errors: Partial<Record<string, string>>;
    options: ClientVehicleOptions;
    showNotes?: boolean;
};

const t = fr.clients.fields;

/** Champs d'un client : téléphone d'abord (c'est par lui qu'on le retrouve). */
export function ClientFields({
    data,
    setData,
    errors,
    options,
    showNotes = true,
}: Props) {
    const wilayas = options.wilayas.map((wilaya) => ({
        value: String(wilaya.id),
        label: `${wilaya.code} ${wilaya.name}`,
    }));

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <FormField
                id="client-phone"
                label={t.phone}
                required
                error={errors.phone}
                className="sm:col-span-2"
            >
                <Input
                    id="client-phone"
                    type="tel"
                    inputMode="tel"
                    autoComplete="off"
                    className="h-11"
                    value={data.phone}
                    onChange={(event) => setData('phone', event.target.value)}
                    aria-invalid={Boolean(errors.phone)}
                    autoFocus
                />
            </FormField>
            <FormField
                id="client-last-name"
                label={t.lastName}
                required
                error={errors.last_name}
            >
                <Input
                    id="client-last-name"
                    className="h-11"
                    value={data.last_name}
                    onChange={(event) =>
                        setData('last_name', event.target.value)
                    }
                    aria-invalid={Boolean(errors.last_name)}
                />
            </FormField>
            <FormField
                id="client-first-name"
                label={t.firstName}
                error={errors.first_name}
            >
                <Input
                    id="client-first-name"
                    className="h-11"
                    value={data.first_name}
                    onChange={(event) =>
                        setData('first_name', event.target.value)
                    }
                    aria-invalid={Boolean(errors.first_name)}
                />
            </FormField>
            <FormField
                id="client-wilaya"
                label={t.wilaya}
                error={errors.wilaya_id}
                className="sm:col-span-2"
            >
                <SearchSelect
                    id="client-wilaya"
                    value={
                        wilayas.find(
                            (option) => option.value === data.wilaya_id,
                        ) ?? null
                    }
                    onChange={(option) =>
                        setData('wilaya_id', option?.value ?? '')
                    }
                    options={wilayas}
                    aria-invalid={Boolean(errors.wilaya_id)}
                />
            </FormField>
            {showNotes && (
                <FormField
                    id="client-notes"
                    label={t.notes}
                    error={errors.notes}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="client-notes"
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                    />
                </FormField>
            )}
        </div>
    );
}
