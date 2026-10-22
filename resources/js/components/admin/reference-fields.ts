import { fr } from '@/lib/i18n/fr';

export type ReferenceTypeValue =
    | 'brands'
    | 'vehicle-models'
    | 'energies'
    | 'service-types'
    | 'article-categories'
    | 'suppliers'
    | 'expense-categories'
    | 'payment-methods'
    | 'wilayas';

export type FieldKind =
    | 'code'
    | 'text'
    | 'textarea'
    | 'money'
    | 'km'
    | 'liters'
    | 'boolean'
    | 'brand';

export type Field = {
    name: string;
    label: string;
    kind: FieldKind;
    required?: boolean;
    hint?: string;
};

export type ReferenceRow = {
    id: number;
    name: string;
    is_active: boolean;
    system_key?: string | null;
    brand?: { id: number; name: string } | null;
    [key: string]: unknown;
};

const f = fr.admin.fields;
const name: Field = {
    name: 'name',
    label: f.name,
    kind: 'text',
    required: true,
};

/** Champs de chaque référentiel : formulaire du panneau latéral et colonnes de la liste. */
export const referenceFields: Record<ReferenceTypeValue, Field[]> = {
    brands: [name],
    'vehicle-models': [
        { name: 'brand_id', label: f.brand, kind: 'brand', required: true },
        name,
        {
            name: 'oil_capacity_l',
            label: f.oilCapacity,
            kind: 'liters',
            hint: f.oilCapacityHint,
        },
    ],
    energies: [name],
    'service-types': [
        name,
        {
            name: 'default_labor_price',
            label: f.laborPrice,
            kind: 'money',
            required: true,
        },
        {
            name: 'requires_mileage',
            label: f.requiresMileage,
            kind: 'boolean',
            hint: f.requiresMileageHint,
        },
        {
            name: 'reminder_km',
            label: f.reminderKm,
            kind: 'km',
            hint: f.reminderKmHint,
        },
    ],
    'article-categories': [name],
    suppliers: [
        name,
        { name: 'phone', label: f.phone, kind: 'text' },
        { name: 'notes', label: f.notes, kind: 'textarea' },
    ],
    'expense-categories': [name],
    'payment-methods': [
        name,
        {
            name: 'is_cash',
            label: f.isCash,
            kind: 'boolean',
            hint: f.isCashHint,
        },
    ],
    wilayas: [
        { name: 'code', label: f.code, kind: 'code', required: true },
        name,
    ],
};

/** Texte d'une colonne simple ('' si vide ou non scalaire). */
export function cellText(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}

/** Valeurs du formulaire : tout en chaîne / booléen, comme les champs HTML. */
export type FormValues = Record<string, string | boolean>;

export function emptyValues(fields: Field[]): FormValues {
    return Object.fromEntries(
        fields.map((field) => [
            field.name,
            field.kind === 'boolean'
                ? false
                : field.kind === 'money'
                  ? '0'
                  : '',
        ]),
    );
}

export function rowValues(fields: Field[], row: ReferenceRow): FormValues {
    return Object.fromEntries(
        fields.map((field) => {
            const value = row[field.name];

            return [
                field.name,
                field.kind === 'boolean' ? Boolean(value) : cellText(value),
            ];
        }),
    );
}

/** Champs vides → null, pour que le serveur enregistre « non renseigné ». */
export function toPayload(
    fields: Field[],
    values: FormValues,
): Record<string, string | boolean | null> {
    return Object.fromEntries(
        fields.map((field) => {
            const value = values[field.name];

            return [field.name, value === '' ? null : value];
        }),
    );
}
