import { useForm } from '@inertiajs/react';
import ArticleController from '@/actions/App/Http/Controllers/Stock/ArticleController';
import { FormField } from '@/components/app/form-field';
import { QuantityInput } from '@/components/app/quantity-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    article: { id: number; name: string; stock_qty: string; unit: string };
};

const t = fr.stock.adjust;

/** Correction : on saisit le stock compté, l'écart s'affiche avant d'enregistrer. Remonté à chaque ouverture (key). */
export function AdjustStockSheet({ open, onOpenChange, article }: Props) {
    const form = useForm({ counted_quantity: '', reason: '' });
    const difference =
        form.data.counted_quantity === ''
            ? null
            : Number(form.data.counted_quantity) - Number(article.stock_qty);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.submit(ArticleController.adjust(article.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>{t.title}</SheetTitle>
                        <SheetDescription>{t.description}</SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-4">
                        <p className="font-medium">{article.name}</p>
                        <dl className="grid grid-cols-2 gap-3 rounded-xl border bg-card p-3 text-sm">
                            <div>
                                <dt className="text-muted-foreground">
                                    {t.current}
                                </dt>
                                <dd className="text-lg font-semibold tabular-nums">
                                    {formatQuantity(
                                        article.stock_qty,
                                        article.unit,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t.difference}
                                </dt>
                                <dd
                                    className={cn(
                                        'text-lg font-semibold tabular-nums',
                                        difference !== null &&
                                            difference > 0 &&
                                            'text-success',
                                        difference !== null &&
                                            difference < 0 &&
                                            'text-destructive',
                                    )}
                                >
                                    {difference === null
                                        ? '—'
                                        : `${difference > 0 ? '+' : ''}${formatQuantity(difference, article.unit)}`}
                                </dd>
                            </div>
                        </dl>
                        <FormField
                            id="adjust-counted"
                            label={t.counted}
                            required
                            error={form.errors.counted_quantity}
                        >
                            <QuantityInput
                                id="adjust-counted"
                                unit={article.unit}
                                value={form.data.counted_quantity}
                                onValueChange={(value) =>
                                    form.setData('counted_quantity', value)
                                }
                                aria-invalid={Boolean(
                                    form.errors.counted_quantity,
                                )}
                                autoFocus
                            />
                        </FormField>
                        <FormField
                            id="adjust-reason"
                            label={t.reason}
                            required
                            error={form.errors.reason}
                        >
                            <Input
                                id="adjust-reason"
                                className="h-11"
                                placeholder={t.reasonPlaceholder}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.reason)}
                            />
                        </FormField>
                    </div>
                    <SheetFooter className="mt-auto flex-row gap-2 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11 flex-1"
                            onClick={() => onOpenChange(false)}
                        >
                            {fr.common.cancel}
                        </Button>
                        <Button
                            type="submit"
                            className="h-11 flex-1"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {fr.common.save}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
