import { useState } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';

export type Rankings = {
    services: { name: string; count: number }[];
    oils: { name: string; liters: string; cans: number }[];
    parts: { name: string; quantity: string; unit: string }[];
    mechanics: { name: string; count: number; orders: number }[];
};

type Tab = keyof Rankings;

const t = fr.dashboard;

/** Une ligne : nom, valeur, et une barre proportionnelle au premier. */
type Row = { name: string; size: number; value: string };

function rows(rankings: Rankings, tab: Tab): Row[] {
    switch (tab) {
        case 'services':
            return rankings.services.map((row) => ({
                name: row.name,
                size: row.count,
                value: t.times(row.count),
            }));
        case 'oils':
            return rankings.oils.map((row) => ({
                name: row.name,
                size: Number(row.liters),
                value: `${formatQuantity(row.liters, 'L')} · ${t.cans(row.cans)}`,
            }));
        case 'parts':
            return rankings.parts.map((row) => ({
                name: row.name,
                size: Number(row.quantity),
                value: formatQuantity(row.quantity, row.unit),
            }));
        case 'mechanics':
            return rankings.mechanics.map((row) => ({
                name: row.name,
                size: row.count,
                value: t.works(row.count, row.orders),
            }));
    }
}

export function RankingsPanel({ rankings }: { rankings: Rankings }) {
    const [tab, setTab] = useState<Tab>('services');
    const list = rows(rankings, tab);
    const max = Math.max(...list.map((row) => row.size), 1);

    return (
        <>
            <ToggleGroup
                type="single"
                value={tab}
                onValueChange={(value: Tab | '') => value && setTab(value)}
                className="-mx-1 flex w-auto justify-start overflow-x-auto px-1 pb-1"
                aria-label={t.rankings}
            >
                {(Object.keys(t.rankingTabs) as Tab[]).map((key) => (
                    <ToggleGroupItem
                        key={key}
                        value={key}
                        className="h-11 shrink-0 rounded-md border border-input bg-background px-3 shadow-xs data-[state=on]:border-primary data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                    >
                        {t.rankingTabs[key]}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            {list.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    {t.rankingEmpty}
                </p>
            ) : (
                <ol className="mt-3 space-y-3">
                    {list.map((row, index) => (
                        <li key={row.name} className="text-sm">
                            <div className="flex items-baseline gap-2">
                                <span className="w-4 shrink-0 text-muted-foreground tabular-nums">
                                    {index + 1}
                                </span>
                                <span className="min-w-0 flex-1 truncate font-medium">
                                    {row.name}
                                </span>
                                <span className="shrink-0 text-muted-foreground tabular-nums">
                                    {row.value}
                                </span>
                            </div>
                            <div className="ms-6 mt-1 h-1.5 rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-primary"
                                    style={{
                                        width: `${(row.size / max) * 100}%`,
                                    }}
                                />
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </>
    );
}
