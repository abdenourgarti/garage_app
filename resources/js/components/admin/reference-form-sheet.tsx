import { useForm } from '@inertiajs/react';
import ReferenceController from '@/actions/App/Http/Controllers/Admin/ReferenceController';
import {
    emptyValues,
    referenceFields,
    rowValues,
    toPayload,
} from '@/components/admin/reference-fields';
import type {
    Field,
    FormValues,
    ReferenceRow,
    ReferenceTypeValue,
} from '@/components/admin/reference-fields';
import { MoneyInput } from '@/components/app/money-input';
import { QuantityInput } from '@/components/app/quantity-input';
import { SearchSelect } from '@/components/app/search-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Textarea } from '@/components/ui/textarea';
import { fr } from '@/lib/i18n/fr';

type Props = {
    type: ReferenceTypeValue;
    typeLabel: string;
    /** null : fermé ; 'new' : ajout ; une ligne : modification. */
    editing: ReferenceRow | 'new' | null;
    onClose: () => void;
    brands: { id: number; name: string; is_active: boolean }[];
};

export function ReferenceFormSheet({
    type,
    typeLabel,
    editing,
    onClose,
    brands,
}: Props) {
    const fields = referenceFields[type];
    // La page remonte ce composant à chaque ouverture (key) : valeurs initiales toujours fraîches.
    const form = useForm<FormValues>(
        editing === null || editing === 'new'
            ? emptyValues(fields)
            : rowValues(fields, editing),
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((values) => toPayload(fields, values));
        form.submit(
            editing === 'new' || editing === null
                ? ReferenceController.store({ type })
                : ReferenceController.update({ type, id: editing.id }),
            { preserveScroll: true, onSuccess: onClose },
        );
    };

    const renderInput = (field: Field) => {
        const id = `reference-${field.name}`;
        const value = form.data[field.name];
        const invalid = Boolean(form.errors[field.name]);

        switch (field.kind) {
            case 'boolean':
                return (
                    <Switch
                        id={id}
                        checked={value === true}
                        onCheckedChange={(checked) =>
                            form.setData(field.name, checked)
                        }
                    />
                );
            case 'money':
                return (
                    <MoneyInput
                        id={id}
                        value={String(value)}
                        onValueChange={(next) => form.setData(field.name, next)}
                        aria-invalid={invalid}
                    />
                );
            case 'liters':
                return (
                    <QuantityInput
                        id={id}
                        value={String(value)}
                        onValueChange={(next) => form.setData(field.name, next)}
                        aria-invalid={invalid}
                    />
                );
            case 'km':
                return (
                    <QuantityInput
                        id={id}
                        unit="km"
                        value={String(value)}
                        onValueChange={(next) => form.setData(field.name, next)}
                        aria-invalid={invalid}
                    />
                );
            case 'brand': {
                const options = brands
                    .filter(
                        (brand) =>
                            brand.is_active || String(brand.id) === value,
                    )
                    .map((brand) => ({
                        value: String(brand.id),
                        label: brand.name,
                    }));

                return (
                    <SearchSelect
                        id={id}
                        value={
                            options.find((option) => option.value === value) ??
                            null
                        }
                        onChange={(option) =>
                            form.setData(field.name, option?.value ?? '')
                        }
                        options={options}
                        aria-invalid={invalid}
                    />
                );
            }
            case 'textarea':
                return (
                    <Textarea
                        id={id}
                        value={String(value)}
                        onChange={(event) =>
                            form.setData(field.name, event.target.value)
                        }
                        aria-invalid={invalid}
                    />
                );
            default:
                return (
                    <Input
                        id={id}
                        value={String(value)}
                        onChange={(event) =>
                            form.setData(field.name, event.target.value)
                        }
                        type={
                            field.kind === 'email' || field.kind === 'url'
                                ? field.kind
                                : 'text'
                        }
                        inputMode={
                            field.kind === 'code' ? 'numeric' : undefined
                        }
                        maxLength={field.kind === 'code' ? 2 : undefined}
                        className="h-11"
                        aria-invalid={invalid}
                    />
                );
        }
    };

    return (
        <Sheet
            open={editing !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <form onSubmit={submit} className="flex h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>
                            {editing === 'new'
                                ? fr.admin.newItem
                                : fr.admin.editItem}
                        </SheetTitle>
                        <SheetDescription>{typeLabel}</SheetDescription>
                    </SheetHeader>

                    <div className="grid gap-5 px-4">
                        {fields.map((field) => (
                            <div
                                key={field.name}
                                className={
                                    field.kind === 'boolean'
                                        ? 'flex items-start justify-between gap-4'
                                        : 'grid gap-2'
                                }
                            >
                                <div className="grid gap-1">
                                    <Label htmlFor={`reference-${field.name}`}>
                                        {field.label}
                                        {field.required && (
                                            <span
                                                className="text-destructive"
                                                aria-hidden="true"
                                            >
                                                {' '}
                                                *
                                            </span>
                                        )}
                                    </Label>
                                    {field.kind === 'boolean' && field.hint && (
                                        <p className="text-sm text-muted-foreground">
                                            {field.hint}
                                        </p>
                                    )}
                                </div>
                                {renderInput(field)}
                                {field.kind !== 'boolean' && field.hint && (
                                    <p className="text-sm text-muted-foreground">
                                        {field.hint}
                                    </p>
                                )}
                                <InputError message={form.errors[field.name]} />
                            </div>
                        ))}
                    </div>

                    <SheetFooter className="mt-auto flex-row gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11 flex-1"
                            onClick={onClose}
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
