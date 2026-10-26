import { useState } from 'react';
import { FormField } from '@/components/app/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { fr } from '@/lib/i18n/fr';
import type { ClientVehicleOptions } from '@/types/clients';

/** Filtres avancés tels qu'ils voyagent dans l'adresse (texte ; types séparés par des virgules). */
export type AdvancedFilters = {
    client: string;
    wilaya: string;
    plate: string;
    brand: string;
    model: string;
    year_from: string;
    year_to: string;
    from: string;
    to: string;
    types: string[];
};

const t = fr.serviceOrders.filters;
const ALL = 'all';

export const emptyFilters: AdvancedFilters = {
    client: '',
    wilaya: '',
    plate: '',
    brand: '',
    model: '',
    year_from: '',
    year_to: '',
    from: '',
    to: '',
    types: [],
};

/**
 * Panneau « Recherche avancée » (CDC M9) : on remplit, puis « Afficher les résultats ».
 * Remonté à chaque ouverture (key) : il repart des filtres appliqués.
 */
export function FiltersSheet({
    open,
    onOpenChange,
    initial,
    options,
    serviceTypes,
    onApply,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initial: AdvancedFilters;
    options: Pick<ClientVehicleOptions, 'wilayas' | 'brands' | 'models'>;
    serviceTypes: { id: number; name: string }[];
    onApply: (filters: AdvancedFilters) => void;
}) {
    const [data, setData] = useState(initial);
    const set = (patch: Partial<AdvancedFilters>) =>
        setData((current) => ({ ...current, ...patch }));
    const models = options.models.filter(
        (model) => String(model.brand_id) === data.brand,
    );

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <form
                    className="flex min-h-full flex-col"
                    onSubmit={(event) => {
                        event.preventDefault();
                        onApply(data);
                    }}
                >
                    <SheetHeader>
                        <SheetTitle>{t.title}</SheetTitle>
                        <SheetDescription>{t.description}</SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-4">
                        <FormField id="filter-client" label={t.client}>
                            <Input
                                id="filter-client"
                                className="h-11"
                                value={data.client}
                                onChange={(event) =>
                                    set({ client: event.target.value })
                                }
                            />
                        </FormField>
                        <FormField id="filter-wilaya" label={t.wilaya}>
                            <Select
                                value={data.wilaya || ALL}
                                onValueChange={(value) =>
                                    set({ wilaya: value === ALL ? '' : value })
                                }
                            >
                                <SelectTrigger
                                    id="filter-wilaya"
                                    className="h-11 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {t.allWilayas}
                                    </SelectItem>
                                    {options.wilayas.map((wilaya) => (
                                        <SelectItem
                                            key={wilaya.id}
                                            value={String(wilaya.id)}
                                        >
                                            {`${wilaya.code} ${wilaya.name}`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField id="filter-plate" label={t.plate}>
                            <Input
                                id="filter-plate"
                                className="h-11"
                                value={data.plate}
                                onChange={(event) =>
                                    set({ plate: event.target.value })
                                }
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-3">
                            <FormField id="filter-brand" label={t.brand}>
                                <Select
                                    value={data.brand || ALL}
                                    onValueChange={(value) =>
                                        set({
                                            brand: value === ALL ? '' : value,
                                            model: '',
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id="filter-brand"
                                        className="h-11 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            {t.allBrands}
                                        </SelectItem>
                                        {options.brands.map((brand) => (
                                            <SelectItem
                                                key={brand.id}
                                                value={String(brand.id)}
                                            >
                                                {brand.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField id="filter-model" label={t.model}>
                                <Select
                                    value={data.model || ALL}
                                    onValueChange={(value) =>
                                        set({
                                            model: value === ALL ? '' : value,
                                        })
                                    }
                                    disabled={!data.brand}
                                >
                                    <SelectTrigger
                                        id="filter-model"
                                        className="h-11 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            {t.allModels}
                                        </SelectItem>
                                        {models.map((model) => (
                                            <SelectItem
                                                key={model.id}
                                                value={String(model.id)}
                                            >
                                                {model.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>
                        <fieldset className="grid gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                {t.year}
                            </legend>
                            <div className="grid grid-cols-2 gap-3">
                                <Input
                                    aria-label={`${t.year} : ${t.yearFrom}`}
                                    placeholder={t.yearFrom}
                                    inputMode="numeric"
                                    maxLength={4}
                                    className="h-11"
                                    value={data.year_from}
                                    onChange={(event) =>
                                        set({
                                            year_from:
                                                event.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                        })
                                    }
                                />
                                <Input
                                    aria-label={`${t.year} : ${t.yearTo}`}
                                    placeholder={t.yearTo}
                                    inputMode="numeric"
                                    maxLength={4}
                                    className="h-11"
                                    value={data.year_to}
                                    onChange={(event) =>
                                        set({
                                            year_to: event.target.value.replace(
                                                /\D/g,
                                                '',
                                            ),
                                        })
                                    }
                                />
                            </div>
                        </fieldset>
                        <fieldset className="grid gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                {t.period}
                            </legend>
                            <div className="grid grid-cols-2 gap-3">
                                <FormField id="filter-from" label={t.from}>
                                    <Input
                                        id="filter-from"
                                        type="date"
                                        className="h-11"
                                        value={data.from}
                                        onChange={(event) =>
                                            set({ from: event.target.value })
                                        }
                                    />
                                </FormField>
                                <FormField id="filter-to" label={t.to}>
                                    <Input
                                        id="filter-to"
                                        type="date"
                                        className="h-11"
                                        value={data.to}
                                        onChange={(event) =>
                                            set({ to: event.target.value })
                                        }
                                    />
                                </FormField>
                            </div>
                        </fieldset>
                        <fieldset className="grid gap-1">
                            <legend className="mb-2 text-sm font-medium">
                                {t.types}
                            </legend>
                            {serviceTypes.map((type) => {
                                const id = String(type.id);
                                const checked = data.types.includes(id);

                                return (
                                    <label
                                        key={type.id}
                                        className="flex min-h-11 items-center gap-3 rounded-md px-2 hover:bg-accent"
                                    >
                                        <Checkbox
                                            checked={checked}
                                            onCheckedChange={() =>
                                                set({
                                                    types: checked
                                                        ? data.types.filter(
                                                              (value) =>
                                                                  value !== id,
                                                          )
                                                        : [...data.types, id],
                                                })
                                            }
                                        />
                                        {type.name}
                                    </label>
                                );
                            })}
                        </fieldset>
                    </div>
                    <SheetFooter className="mt-auto flex-row gap-2 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11 flex-1"
                            onClick={() => onApply(emptyFilters)}
                        >
                            {t.clear}
                        </Button>
                        <Button type="submit" className="h-11 flex-1">
                            {t.apply}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
