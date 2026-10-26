import {
    Bar,
    CartesianGrid,
    ComposedChart,
    Line,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatMoney } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';

export type MonthTotals = {
    month: string;
    incomes: string;
    expenses: string;
    profit: string;
};

// Couleurs vérifiées (lisibles par les daltoniens, en clair et en sombre) ; le bénéfice garde la couleur du texte.
const config = {
    incomes: { label: fr.cash.incomes, color: '#1583A3' },
    expenses: { label: fr.cash.expenses, color: '#D0740A' },
    profit: { label: fr.cash.profit, color: 'var(--foreground)' },
} satisfies ChartConfig;

const monthName = (month: string, style: 'short' | 'long') =>
    new Date(`${month}-01T12:00:00`).toLocaleDateString('fr-DZ', {
        month: style,
        year: style === 'long' ? 'numeric' : undefined,
    });

/** « 120 k » : axe court, les montants exacts sont dans l'infobulle et le tableau. */
const compact = (value: number) =>
    new Intl.NumberFormat('fr-DZ', { notation: 'compact' }).format(value);

/** Graphique des 12 derniers mois (gérant) : un seul axe, tout est en DA. */
export function MonthsChart({ months }: { months: MonthTotals[] }) {
    const data = months.map((row) => ({
        month: row.month,
        incomes: Number(row.incomes),
        expenses: Number(row.expenses),
        profit: Number(row.profit),
    }));

    return (
        <>
            <ChartContainer
                config={config}
                className="aspect-auto h-56 w-full sm:h-72"
            >
                <ComposedChart data={data} margin={{ left: 0, right: 4 }}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="month"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        minTickGap={4}
                        tickFormatter={(month: string) =>
                            monthName(month, 'short')
                        }
                    />
                    <YAxis
                        width={44}
                        tickLine={false}
                        axisLine={false}
                        tickFormatter={compact}
                    />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                labelFormatter={(_, payload) =>
                                    monthName(
                                        String(payload[0]?.payload.month),
                                        'long',
                                    )
                                }
                                formatter={(value, name, item) => (
                                    <div className="flex w-full items-center gap-2">
                                        <span
                                            className="size-2.5 shrink-0 rounded-[2px]"
                                            style={{ background: item.color }}
                                        />
                                        <span className="text-muted-foreground">
                                            {
                                                config[
                                                    name as keyof typeof config
                                                ].label
                                            }
                                        </span>
                                        <span className="ms-auto font-medium tabular-nums">
                                            {formatMoney(Number(value))}
                                        </span>
                                    </div>
                                )}
                            />
                        }
                    />
                    <ChartLegend
                        content={<ChartLegendContent />}
                        itemSorter={null}
                    />
                    <Bar
                        dataKey="incomes"
                        fill="var(--color-incomes)"
                        radius={[4, 4, 0, 0]}
                        maxBarSize={18}
                    />
                    <Bar
                        dataKey="expenses"
                        fill="var(--color-expenses)"
                        radius={[4, 4, 0, 0]}
                        maxBarSize={18}
                    />
                    <Line
                        dataKey="profit"
                        type="monotone"
                        stroke="var(--color-profit)"
                        strokeWidth={2}
                        dot={{ r: 3, fill: 'var(--color-profit)' }}
                        activeDot={{ r: 5 }}
                    />
                </ComposedChart>
            </ChartContainer>

            <table className="sr-only">
                <caption>{fr.dashboard.monthsTable}</caption>
                <thead>
                    <tr>
                        <th scope="col">{fr.dashboard.month}</th>
                        <th scope="col">{fr.cash.incomes}</th>
                        <th scope="col">{fr.cash.expenses}</th>
                        <th scope="col">{fr.cash.profit}</th>
                    </tr>
                </thead>
                <tbody>
                    {months.map((row) => (
                        <tr key={row.month}>
                            <th scope="row">{monthName(row.month, 'long')}</th>
                            <td>{formatMoney(row.incomes)}</td>
                            <td>{formatMoney(row.expenses)}</td>
                            <td>{formatMoney(row.profit)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </>
    );
}
