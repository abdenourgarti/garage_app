/** Listes envoyées par App\Support\FormOptions::clientVehicle(). */
export type ClientVehicleOptions = {
    wilayas: { id: number; code: string; name: string }[];
    brands: { id: number; name: string }[];
    models: { id: number; brand_id: number; name: string }[];
    energies: { id: number; name: string }[];
};

/** Champs client du formulaire (valeurs texte, comme les champs HTML). */
export type ClientFormData = {
    last_name: string;
    first_name: string;
    phone: string;
    wilaya_id: string;
    notes: string;
};

/** Champs véhicule du formulaire. */
export type VehicleFormData = {
    plate: string;
    vin: string;
    brand_id: string;
    vehicle_model_id: string;
    year: string;
    energy_id: string;
    last_mileage: string;
    notes: string;
};

export const emptyClient: ClientFormData = {
    last_name: '',
    first_name: '',
    phone: '',
    wilaya_id: '',
    notes: '',
};

export const emptyVehicle: VehicleFormData = {
    plate: '',
    vin: '',
    brand_id: '',
    vehicle_model_id: '',
    year: '',
    energy_id: '',
    last_mileage: '',
    notes: '',
};

/** Valeurs vides → null pour le serveur. */
export function nullable<T extends Record<string, unknown>>(
    data: T,
): Record<string, unknown> {
    return Object.fromEntries(
        Object.entries(data).map(([key, value]) => [
            key,
            value === '' ? null : value,
        ]),
    );
}
