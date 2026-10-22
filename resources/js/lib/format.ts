/**
 * Formats d'affichage (docs/02-conception-technique.md §3).
 * Les montants arrivent du serveur en chaîne décimale ("12500.00") : on ne calcule jamais ici.
 */
type Numeric = number | string | null | undefined;

const NBSP = ' ';

function toNumber(value: Numeric): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(number) ? number : null;
}

function formatNumber(number: number, maxDecimals: number): string {
    return new Intl.NumberFormat('fr-DZ', {
        minimumFractionDigits: 0,
        maximumFractionDigits: maxDecimals,
    }).format(number);
}

/** 12500 → « 12 500 DA » ; 12500.5 → « 12 500,50 DA » ; null → « — ». */
export function formatMoney(value: Numeric): string {
    const number = toNumber(value);

    if (number === null) {
        return '—';
    }

    const decimals = Number.isInteger(number) ? 0 : 2;
    const formatted = new Intl.NumberFormat('fr-DZ', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(number);

    return `${formatted}${NBSP}DA`;
}

/** 4.50 → « 4,5 » ; avec unité : « 4,5 L ». */
export function formatQuantity(value: Numeric, unit?: string): string {
    const number = toNumber(value);

    if (number === null) {
        return '—';
    }

    const formatted = formatNumber(number, 2);

    return unit ? `${formatted}${NBSP}${unit}` : formatted;
}

function toDate(value: string | Date): Date {
    if (value instanceof Date) {
        return value;
    }

    // "2026-10-22" : date seule, lue en heure locale (sinon décalage d'un jour selon le fuseau).
    const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (dateOnly) {
        return new Date(
            Number(dateOnly[1]),
            Number(dateOnly[2]) - 1,
            Number(dateOnly[3]),
        );
    }

    return new Date(value);
}

/** « 22/10/2026 ». */
export function formatDate(value: string | Date | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(toDate(value));
}

/** « 14:05 ». */
export function formatTime(value: string | Date | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(toDate(value));
}

/** Saisie utilisateur « 12 500,5 » → « 12500.5 » (chaîne envoyée au serveur), ou '' si invalide. */
export function parseDecimal(input: string): string {
    const cleaned = input.replace(/[\s  ]/g, '').replace(',', '.');

    return /^-?\d*\.?\d*$/.test(cleaned) && cleaned !== '' && cleaned !== '.'
        ? cleaned
        : '';
}

/** Date locale → « 2026-10-22 » pour les champs date et la query string. */
export function toIsoDate(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}
