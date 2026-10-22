import { FormField } from '@/components/app/form-field';
import { QuantityInput } from '@/components/app/quantity-input';
import { SearchSelect } from '@/components/app/search-select';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { fr } from '@/lib/i18n/fr';
import type { ClientVehicleOptions, VehicleFormData } from '@/types/clients';

type Props = {
    data: VehicleFormData;
    setData: (field: keyof VehicleFormData, value: string) => void;
    errors: Partial<Record<string, string>>;
    options: ClientVehicleOptions;
    showNotes?: boolean;
    /** Libellés de la marque / du modèle actuels s'ils sont désactivés (absents des listes). */
    current?: { brand?: string; model?: string | null };
};

const t = fr.vehicles.fields;

/** Champs d'un véhicule : matricule, marque puis modèle filtré par la marque. */
export function VehicleFields({
    data,
    setData,
    errors,
    options,
    showNotes = true,
    current,
}: Props) {
    const brands = options.brands.map((brand) => ({
        value: String(brand.id),
        label: brand.name,
    }));
    const models = options.models
        .filter((model) => String(model.brand_id) === data.brand_id)
        .map((model) => ({ value: String(model.id), label: model.name }));
    const energies = options.energies.map((energy) => ({
        value: String(energy.id),
        label: energy.name,
    }));

    const selected = (
        list: { value: string; label: string }[],
        value: string,
        fallback?: string | null,
    ) =>
        list.find((option) => option.value === value) ??
        (value && fallback ? { value, label: fallback } : null);

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <FormField
                id="vehicle-plate"
                label={t.plate}
                required
                hint={t.plateHint}
                error={errors.plate}
                className="sm:col-span-2"
            >
                <Input
                    id="vehicle-plate"
                    autoComplete="off"
                    className="h-11 font-semibold tracking-wide uppercase"
                    value={data.plate}
                    onChange={(event) => setData('plate', event.target.value)}
                    aria-invalid={Boolean(errors.plate)}
                />
            </FormField>
            <FormField
                id="vehicle-brand"
                label={t.brand}
                required
                error={errors.brand_id}
            >
                <SearchSelect
                    id="vehicle-brand"
                    value={selected(brands, data.brand_id, current?.brand)}
                    onChange={(option) => {
                        setData('brand_id', option?.value ?? '');
                        setData('vehicle_model_id', '');
                    }}
                    options={brands}
                    aria-invalid={Boolean(errors.brand_id)}
                />
            </FormField>
            <FormField
                id="vehicle-model"
                label={t.model}
                error={errors.vehicle_model_id}
            >
                <SearchSelect
                    id="vehicle-model"
                    value={selected(
                        models,
                        data.vehicle_model_id,
                        current?.model,
                    )}
                    onChange={(option) =>
                        setData('vehicle_model_id', option?.value ?? '')
                    }
                    options={models}
                    disabled={data.brand_id === ''}
                    placeholder={
                        data.brand_id === '' ? t.modelPlaceholder : undefined
                    }
                    aria-invalid={Boolean(errors.vehicle_model_id)}
                />
            </FormField>
            <FormField id="vehicle-year" label={t.year} error={errors.year}>
                <Input
                    id="vehicle-year"
                    inputMode="numeric"
                    maxLength={4}
                    className="h-11 tabular-nums"
                    value={data.year}
                    onChange={(event) =>
                        setData('year', event.target.value.replace(/\D/g, ''))
                    }
                    aria-invalid={Boolean(errors.year)}
                />
            </FormField>
            <FormField
                id="vehicle-energy"
                label={t.energy}
                error={errors.energy_id}
            >
                <SearchSelect
                    id="vehicle-energy"
                    value={selected(energies, data.energy_id)}
                    onChange={(option) =>
                        setData('energy_id', option?.value ?? '')
                    }
                    options={energies}
                    aria-invalid={Boolean(errors.energy_id)}
                />
            </FormField>
            <FormField
                id="vehicle-mileage"
                label={t.mileage}
                hint={t.mileageHint}
                error={errors.last_mileage}
            >
                <QuantityInput
                    id="vehicle-mileage"
                    unit="km"
                    value={data.last_mileage}
                    onValueChange={(value) => setData('last_mileage', value)}
                    aria-invalid={Boolean(errors.last_mileage)}
                />
            </FormField>
            <FormField
                id="vehicle-vin"
                label={t.vin}
                hint={t.vinHint}
                error={errors.vin}
            >
                <Input
                    id="vehicle-vin"
                    autoComplete="off"
                    className="h-11 uppercase"
                    value={data.vin}
                    onChange={(event) => setData('vin', event.target.value)}
                    aria-invalid={Boolean(errors.vin)}
                />
            </FormField>
            {showNotes && (
                <FormField
                    id="vehicle-notes"
                    label={t.notes}
                    error={errors.notes}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="vehicle-notes"
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
