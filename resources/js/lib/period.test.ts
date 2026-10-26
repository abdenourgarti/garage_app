import { describe, expect, it } from 'vite-plus/test';
import { periodRange, shiftMonth } from './period';

// Mercredi 22 octobre 2026.
const wednesday = new Date(2026, 9, 22);

describe('periodRange', () => {
    it('returns today', () => {
        expect(periodRange('today', wednesday)).toMatchObject({
            from: '2026-10-22',
            to: '2026-10-22',
        });
    });

    it('returns a Monday to Sunday week', () => {
        expect(periodRange('week', wednesday)).toMatchObject({
            from: '2026-10-19',
            to: '2026-10-25',
        });
        // Un dimanche appartient à la semaine qui finit ce jour-là.
        expect(periodRange('week', new Date(2026, 9, 25))).toMatchObject({
            from: '2026-10-19',
            to: '2026-10-25',
        });
    });

    it('returns the month and the year', () => {
        expect(periodRange('month', new Date(2028, 1, 10))).toMatchObject({
            from: '2028-02-01',
            to: '2028-02-29',
        });
        expect(periodRange('year', wednesday)).toMatchObject({
            from: '2026-01-01',
            to: '2026-12-31',
        });
    });
});

describe('shiftMonth', () => {
    it('moves across years', () => {
        expect(shiftMonth('2026-12', 1)).toBe('2027-01');
        expect(shiftMonth('2026-01', -1)).toBe('2025-12');
        expect(shiftMonth('2026-10', 0)).toBe('2026-10');
    });
});
