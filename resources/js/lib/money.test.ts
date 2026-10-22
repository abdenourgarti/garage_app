import { describe, expect, it } from 'vite-plus/test';
import { previewLineTotal, previewSum } from './money';

describe('money previews', () => {
    it('multiplies quantity and price to the centime', () => {
        expect(previewLineTotal('2.5', '1500')).toBe(3750);
        expect(previewLineTotal('10', '4500')).toBe(45000);
        expect(previewLineTotal('0.333', '10')).toBe(3.3);
        expect(previewLineTotal('', '10')).toBe(0);
    });

    it('sums without floating point drift', () => {
        expect(previewSum(['0.1', '0.2'])).toBe(0.3);
        expect(previewSum([45000, '1500.50'])).toBe(46500.5);
    });
});
