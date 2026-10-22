import { toIsoDate } from '@/lib/format';

export type PeriodPreset = 'today' | 'week' | 'month' | 'year' | 'custom';

export type Period = {
    preset: PeriodPreset;
    /** « 2026-10-01 » */
    from: string;
    to: string;
};

/** Bornes d'une période prête (RG-14) ; la semaine va du lundi au dimanche. */
export function periodRange(
    preset: Exclude<PeriodPreset, 'custom'>,
    today = new Date(),
): Period {
    const y = today.getFullYear();
    const m = today.getMonth();
    const d = today.getDate();

    switch (preset) {
        case 'today':
            return { preset, from: toIsoDate(today), to: toIsoDate(today) };
        case 'week': {
            const mondayOffset = (today.getDay() + 6) % 7;

            return {
                preset,
                from: toIsoDate(new Date(y, m, d - mondayOffset)),
                to: toIsoDate(new Date(y, m, d - mondayOffset + 6)),
            };
        }
        case 'month':
            return {
                preset,
                from: toIsoDate(new Date(y, m, 1)),
                to: toIsoDate(new Date(y, m + 1, 0)),
            };
        case 'year':
            return {
                preset,
                from: toIsoDate(new Date(y, 0, 1)),
                to: toIsoDate(new Date(y, 11, 31)),
            };
    }
}
