/**
 * Aperçu des totaux pendant la saisie (le serveur recalcule et fait foi : App\Support\Decimal).
 * Calcul en centimes entiers pour éviter les erreurs des nombres à virgule (0,1 + 0,2).
 */
const toCents = (value: string | number): number =>
    Math.round(Number(value || 0) * 100);

/** quantité × prix, arrondi au centime : « 2,5 » × « 1500 » → 3750. */
export function previewLineTotal(
    quantity: string | number,
    unitPrice: string | number,
): number {
    return Math.round((toCents(quantity) * toCents(unitPrice)) / 100) / 100;
}

export function previewSum(values: (string | number)[]): number {
    return values.reduce<number>((sum, value) => sum + toCents(value), 0) / 100;
}
