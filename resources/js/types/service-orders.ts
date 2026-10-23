import type { SelectOption } from '@/components/app/search-select';

/** Véhicule choisi sur une prestation (LookupController::serviceVehicleOption). */
export type ServiceVehicleOption = SelectOption & {
    vehicle_label?: string;
    client?: { id: number; full_name: string; phone: string };
    last_mileage?: number | null;
    oil_capacity_l?: string | null;
};

export type ServiceTypeOption = {
    id: number;
    name: string;
    default_labor_price: string;
    requires_mileage: boolean;
};

/** Article choisi dans une ligne de pièce (stock ou format d'huile). */
export type PartArticle = SelectOption & {
    unit?: string;
    stock_qty?: string;
    sale_price?: string;
};

export type OilOption = SelectOption & {
    formats: {
        article_id: string;
        label: string;
        format: string;
        format_label: string;
        unit: string;
        stock_qty: string;
        sale_price: string;
    }[];
};

export type PartRowData = {
    key: number;
    origin: 'stock' | 'client' | 'external';
    /** « + Huile » : on choisit l'huile puis le format, la ligne devient une pièce du stock. */
    oil: OilOption | null;
    isOil: boolean;
    article: PartArticle | null;
    designation: string;
    quantity: string;
    unit_price: string;
    unit_cost: string;
};

export type LineData = {
    key: number;
    service_type_id: number;
    employee_id: string;
    description: string;
    labor_price: string;
    parts: PartRowData[];
};
