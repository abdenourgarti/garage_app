import { describe, expect, it } from 'vite-plus/test';
import {
    formatDate,
    formatMoney,
    formatQuantity,
    formatTime,
    parseDecimal,
    toIsoDate,
} from './format';

// Intl insère des espaces insécables (U+00A0 / U+202F) : on les normalise pour comparer.
const plain = (value: string) => value.replace(/[  ]/g, ' ');

describe('formatMoney', () => {
    it('formats whole amounts without decimals', () => {
        expect(plain(formatMoney(12500))).toBe('12 500 DA');
        expect(plain(formatMoney('12500.00'))).toBe('12 500 DA');
        expect(plain(formatMoney(0))).toBe('0 DA');
    });

    it('keeps two decimals when needed', () => {
        expect(plain(formatMoney('12500.5'))).toBe('12 500,50 DA');
    });

    it('formats negative amounts and empty values', () => {
        expect(plain(formatMoney(-3000))).toBe('-3 000 DA');
        expect(formatMoney(null)).toBe('—');
        expect(formatMoney('abc')).toBe('—');
    });
});

describe('formatQuantity', () => {
    it('drops useless zeros', () => {
        expect(formatQuantity('4.50')).toBe('4,5');
        expect(formatQuantity(5)).toBe('5');
        expect(plain(formatQuantity('0.25', 'L'))).toBe('0,25 L');
    });
});

describe('dates', () => {
    it('formats a date-only string without timezone shift', () => {
        expect(formatDate('2026-01-01')).toBe('01/01/2026');
        expect(formatDate(null)).toBe('—');
    });

    it('formats time and ISO dates', () => {
        expect(formatTime(new Date(2026, 9, 22, 14, 5))).toBe('14:05');
        expect(toIsoDate(new Date(2026, 0, 5))).toBe('2026-01-05');
    });
});

describe('parseDecimal', () => {
    it('accepts French input', () => {
        expect(parseDecimal('12 500,5')).toBe('12500.5');
        expect(parseDecimal('1500')).toBe('1500');
    });

    it('rejects invalid input', () => {
        expect(parseDecimal('12a')).toBe('');
        expect(parseDecimal('')).toBe('');
    });
});
