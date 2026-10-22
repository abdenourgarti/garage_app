export type Option = { value: string; label: string };

/** Listes envoyées par ArticleController::formOptions(). */
export type StockOptions = {
    categories: { id: number; name: string }[];
    stockUnits: Option[];
    oilUsages: Option[];
    oilFormats: Option[];
};

/** Fiche huile et ses formats (page article d'un format d'huile). */
export type OilSheet = {
    id: number;
    brand: string;
    name: string | null;
    viscosity: string;
    approval: string | null;
    usage: string;
    usage_label: string;
    label: string;
    is_active: boolean;
    formats: {
        id: number;
        format: string;
        label: string;
        purchase_price: string;
        sale_price: string;
        alert_threshold: string | null;
        stock_qty: string;
        unit: string;
        is_active: boolean;
    }[];
};
