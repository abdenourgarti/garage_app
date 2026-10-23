import { Trash2 } from 'lucide-react';
import { MoneyInput } from '@/components/app/money-input';
import { MoneyText } from '@/components/app/money-text';
import { QuantityInput } from '@/components/app/quantity-input';
import { SearchSelect } from '@/components/app/search-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { previewLineTotal } from '@/lib/money';
import { cn } from '@/lib/utils';
import {
    articles as lookupArticles,
    oils as lookupOils,
} from '@/routes/lookup';
import type {
    OilOption,
    PartArticle,
    PartRowData,
} from '@/types/service-orders';

type Props = {
    part: PartRowData;
    idPrefix: string;
    onChange: (patch: Partial<PartRowData>) => void;
    onRemove: () => void;
    /** Capacité d'huile du modèle : quantité proposée pour le vrac. */
    oilCapacity: string | null;
    errors: (field: string) => string | undefined;
};

const t = fr.serviceOrders;

async function fetchOptions<T>(url: string): Promise<T[]> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    return response.ok ? ((await response.json()) as T[]) : [];
}

const searchArticles = (query: string) =>
    fetchOptions<PartArticle>(lookupArticles.url({ query: { q: query } }));
const searchOils = (query: string) =>
    fetchOptions<OilOption>(lookupOils.url({ query: { q: query } }));

/**
 * Une pièce d'une ligne de travaux, selon son origine : stock (ou huile → format), client, extérieur.
 */
export function PartRow({
    part,
    idPrefix,
    onChange,
    onRemove,
    oilCapacity,
    errors,
}: Props) {
    const unit = part.article?.unit ?? 'u';
    const short =
        part.origin === 'stock' &&
        part.article?.stock_qty !== undefined &&
        Number(part.article.stock_qty) < Number(part.quantity || 0);

    const quantityField = (
        <QuantityInput
            id={`${idPrefix}-qty`}
            unit={unit}
            value={part.quantity}
            onValueChange={(value) => onChange({ quantity: value })}
            aria-label={t.quantity}
            aria-invalid={Boolean(errors('quantity'))}
        />
    );

    return (
        <li className="grid gap-2 rounded-lg border bg-background p-2.5">
            <div className="flex items-start gap-2">
                <div className="min-w-0 flex-1">
                    {part.origin === 'stock' && part.isOil && !part.article && (
                        <div className="grid gap-2">
                            <SearchSelect
                                id={`${idPrefix}-oil`}
                                value={part.oil}
                                onChange={(option) =>
                                    onChange({
                                        oil: option as OilOption | null,
                                    })
                                }
                                loadOptions={searchOils}
                                placeholder={t.oilSearch}
                            />
                            {part.oil && (
                                <div
                                    className="flex flex-wrap gap-2"
                                    role="group"
                                    aria-label={t.chooseFormat}
                                >
                                    {part.oil.formats.map((format) => (
                                        <Button
                                            key={format.article_id}
                                            type="button"
                                            variant="outline"
                                            className="h-11"
                                            onClick={() =>
                                                onChange({
                                                    article: {
                                                        value: format.article_id,
                                                        label: format.label,
                                                        unit: format.unit,
                                                        stock_qty:
                                                            format.stock_qty,
                                                        sale_price:
                                                            format.sale_price,
                                                    },
                                                    unit_price:
                                                        format.sale_price,
                                                    quantity:
                                                        format.format ===
                                                            'bulk' &&
                                                        oilCapacity
                                                            ? oilCapacity
                                                            : '1',
                                                })
                                            }
                                        >
                                            {format.format_label}
                                            <span className="text-xs text-muted-foreground">
                                                {formatQuantity(
                                                    format.stock_qty,
                                                    format.unit,
                                                )}
                                            </span>
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                    {part.origin === 'stock' &&
                        (!part.isOil || part.article) && (
                            <SearchSelect
                                id={`${idPrefix}-article`}
                                value={part.article}
                                onChange={(option) => {
                                    const article =
                                        option as PartArticle | null;
                                    onChange({
                                        article,
                                        unit_price:
                                            article?.sale_price ??
                                            part.unit_price,
                                    });
                                }}
                                loadOptions={searchArticles}
                                placeholder={t.article}
                                aria-invalid={Boolean(errors('article_id'))}
                            />
                        )}
                    {part.origin !== 'stock' && (
                        <Input
                            id={`${idPrefix}-designation`}
                            className="h-11"
                            placeholder={
                                part.origin === 'client'
                                    ? t.origins.client
                                    : t.origins.external
                            }
                            value={part.designation}
                            onChange={(event) =>
                                onChange({ designation: event.target.value })
                            }
                            aria-label={t.designation}
                            aria-invalid={Boolean(errors('designation'))}
                        />
                    )}
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-lg"
                    className="shrink-0 text-destructive hover:text-destructive"
                    onClick={onRemove}
                    aria-label={t.removePart}
                >
                    <Trash2 />
                </Button>
            </div>

            {(part.origin !== 'stock' || part.article) && (
                <div
                    className={cn(
                        'grid items-center gap-2',
                        part.origin === 'external'
                            ? 'grid-cols-2 sm:grid-cols-[7rem_1fr_1fr_auto]'
                            : part.origin === 'client'
                              ? 'grid-cols-[7rem_1fr]'
                              : 'grid-cols-[7rem_1fr_auto]',
                    )}
                >
                    {quantityField}
                    {part.origin === 'external' && (
                        <MoneyInput
                            id={`${idPrefix}-cost`}
                            value={part.unit_cost}
                            onValueChange={(value) =>
                                onChange({ unit_cost: value })
                            }
                            placeholder={t.purchasePrice}
                            aria-label={t.purchasePrice}
                            aria-invalid={Boolean(errors('unit_cost'))}
                        />
                    )}
                    {part.origin !== 'client' && (
                        <MoneyInput
                            id={`${idPrefix}-price`}
                            value={part.unit_price}
                            onValueChange={(value) =>
                                onChange({ unit_price: value })
                            }
                            aria-label={
                                part.origin === 'external'
                                    ? t.billedPrice
                                    : t.price
                            }
                            aria-invalid={Boolean(errors('unit_price'))}
                        />
                    )}
                    {part.origin === 'client' ? (
                        <span className="text-sm text-muted-foreground">
                            {t.clientPartHint}
                        </span>
                    ) : (
                        <MoneyText
                            value={previewLineTotal(
                                part.quantity,
                                part.unit_price,
                            )}
                            className="text-end"
                        />
                    )}
                </div>
            )}

            {part.origin === 'external' && (
                <p className="text-xs text-muted-foreground">
                    {t.externalPartHint}
                </p>
            )}
            {part.article?.stock_qty !== undefined && (
                <p
                    className={cn(
                        'text-xs',
                        short
                            ? 'font-medium text-warning'
                            : 'text-muted-foreground',
                    )}
                >
                    {short
                        ? t.stockShort
                        : t.stockLeft(
                              formatQuantity(part.article.stock_qty, unit),
                          )}
                </p>
            )}
            {[
                'article_id',
                'designation',
                'quantity',
                'unit_price',
                'unit_cost',
            ].map((field) => (
                <InputError key={field} message={errors(field)} />
            ))}
        </li>
    );
}
