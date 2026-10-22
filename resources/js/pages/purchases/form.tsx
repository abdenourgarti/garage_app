import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PurchaseController from '@/actions/App/Http/Controllers/Stock/PurchaseController';
import { FileInput } from '@/components/app/file-input';
import { FormField } from '@/components/app/form-field';
import { MoneyInput } from '@/components/app/money-input';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { QuantityInput } from '@/components/app/quantity-input';
import { SearchSelect } from '@/components/app/search-select';
import type { SelectOption } from '@/components/app/search-select';
import InputError from '@/components/input-error';
import type { PaymentMethodOption } from '@/components/stock/pay-supplier-sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { toIsoDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { previewLineTotal, previewSum } from '@/lib/money';
import { articles as lookupArticles } from '@/routes/lookup';
import {
    index as purchasesIndex,
    show as showPurchase,
} from '@/routes/purchases';

type ArticleOption = SelectOption & { unit?: string; purchase_price?: string };

type Line = {
    key: number;
    article: ArticleOption | null;
    quantity: string;
    unit_price: string;
};

type Props = {
    purchase: {
        id: number;
        supplier_id: number;
        purchased_at: string;
        supplier_invoice_ref: string | null;
        notes: string | null;
        status: string;
        amount_paid: string;
        has_document: boolean;
        items: {
            article: ArticleOption;
            quantity: string;
            unit_price: string;
        }[];
    } | null;
    defaultSupplierId: number | null;
    suppliers: { id: number; name: string }[];
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
};

type Target = 'draft' | 'ordered' | 'received';

const t = fr.purchases;

async function searchArticles(query: string): Promise<ArticleOption[]> {
    const response = await fetch(lookupArticles.url({ query: { q: query } }), {
        headers: { Accept: 'application/json' },
    });

    return response.ok ? ((await response.json()) as ArticleOption[]) : [];
}

let nextKey = 1;
const newLine = (): Line => ({
    key: nextKey++,
    article: null,
    quantity: '1',
    unit_price: '',
});

export default function PurchaseForm({
    purchase,
    defaultSupplierId,
    suppliers,
    paymentMethods,
    defaultPaymentMethodId,
}: Props) {
    const today = toIsoDate(new Date());
    const supplierOptions = suppliers.map((supplier) => ({
        value: String(supplier.id),
        label: supplier.name,
    }));
    const [withPayment, setWithPayment] = useState(false);
    const [target, setTarget] = useState<Target | null>(null);

    const form = useForm({
        supplier_id: String(purchase?.supplier_id ?? defaultSupplierId ?? ''),
        purchased_at: purchase?.purchased_at ?? today,
        supplier_invoice_ref: purchase?.supplier_invoice_ref ?? '',
        notes: purchase?.notes ?? '',
        lines: purchase
            ? purchase.items.map((item) => ({ key: nextKey++, ...item }))
            : [newLine()],
        document: null as File | null,
        paid_amount: '',
        payment_method_id: defaultPaymentMethodId
            ? String(defaultPaymentMethodId)
            : '',
        paid_at: today,
        proof: null as File | null,
    });

    const errors = form.errors as Record<string, string | undefined>;
    const total = previewSum(
        form.data.lines.map((line) =>
            previewLineTotal(line.quantity, line.unit_price),
        ),
    );

    const setLine = (key: number, patch: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((line) =>
                line.key === key ? { ...line, ...patch } : line,
            ),
        );

    const submit = (status: Target) => {
        setTarget(status);
        form.transform(
            (data) =>
                ({
                    ...(purchase ? { _method: 'put' } : {}),
                    supplier_id: data.supplier_id,
                    purchased_at: data.purchased_at,
                    supplier_invoice_ref: data.supplier_invoice_ref || null,
                    notes: data.notes || null,
                    status,
                    lines: data.lines.map((line) => ({
                        article_id: line.article?.value ?? '',
                        quantity: line.quantity,
                        unit_price: line.unit_price,
                    })),
                    document: data.document,
                    ...(withPayment && status !== 'draft'
                        ? {
                              paid_amount: data.paid_amount,
                              payment_method_id: data.payment_method_id,
                              paid_at: data.paid_at,
                              proof: data.proof,
                          }
                        : {}),
                }) as never,
        );
        // Fichiers joints : POST multipart, avec _method=put en modification (PHP ne lit pas un PUT multipart).
        form.post(
            purchase
                ? PurchaseController.update.url(purchase.id)
                : PurchaseController.store.url(),
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setTarget(null),
            },
        );
    };

    return (
        <>
            <Head title={purchase ? t.editPurchase : t.newPurchase} />
            <form
                onSubmit={(event) => event.preventDefault()}
                className="space-y-6 p-4 pb-40 md:p-6 md:pb-6"
            >
                <PageHeader
                    title={
                        purchase
                            ? `${t.editPurchase} n° ${purchase.id}`
                            : t.newPurchase
                    }
                    actions={
                        <Button asChild variant="outline" className="h-11">
                            <Link
                                href={
                                    purchase
                                        ? showPurchase(purchase.id)
                                        : purchasesIndex()
                                }
                            >
                                <ArrowLeft />
                                {fr.common.cancel}
                            </Link>
                        </Button>
                    }
                />

                <section className="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <FormField
                        id="purchase-supplier"
                        label={t.fields.supplier}
                        required
                        error={errors.supplier_id}
                        className="sm:col-span-2"
                    >
                        <SearchSelect
                            id="purchase-supplier"
                            value={
                                supplierOptions.find(
                                    (option) =>
                                        option.value === form.data.supplier_id,
                                ) ?? null
                            }
                            onChange={(option) =>
                                form.setData('supplier_id', option?.value ?? '')
                            }
                            options={supplierOptions}
                            aria-invalid={Boolean(errors.supplier_id)}
                        />
                    </FormField>
                    <FormField
                        id="purchase-date"
                        label={t.fields.date}
                        required
                        error={errors.purchased_at}
                    >
                        <Input
                            id="purchase-date"
                            type="date"
                            className="h-11"
                            max={today}
                            value={form.data.purchased_at}
                            onChange={(event) =>
                                form.setData('purchased_at', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="purchase-ref"
                        label={t.fields.invoiceRef}
                        error={errors.supplier_invoice_ref}
                    >
                        <Input
                            id="purchase-ref"
                            className="h-11"
                            value={form.data.supplier_invoice_ref}
                            onChange={(event) =>
                                form.setData(
                                    'supplier_invoice_ref',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t.lines}</h2>
                    <InputError message={errors.lines} />
                    <ul className="space-y-3">
                        {form.data.lines.map((line, index) => (
                            <li
                                key={line.key}
                                className="grid gap-3 rounded-xl border bg-card p-3 sm:grid-cols-[minmax(0,1fr)_8rem_10rem_8rem_auto] sm:items-end"
                            >
                                <FormField
                                    id={`line-${line.key}-article`}
                                    label={t.fields.article}
                                    required
                                    error={errors[`lines.${index}.article_id`]}
                                >
                                    <SearchSelect
                                        id={`line-${line.key}-article`}
                                        value={line.article}
                                        loadOptions={searchArticles}
                                        onChange={(option) => {
                                            const article =
                                                option as ArticleOption | null;
                                            setLine(line.key, {
                                                article,
                                                unit_price:
                                                    line.unit_price === '' &&
                                                    article?.purchase_price
                                                        ? article.purchase_price
                                                        : line.unit_price,
                                            });
                                        }}
                                        placeholder={fr.stock.search}
                                        aria-invalid={Boolean(
                                            errors[`lines.${index}.article_id`],
                                        )}
                                    />
                                </FormField>
                                <FormField
                                    id={`line-${line.key}-qty`}
                                    label={t.fields.quantity}
                                    required
                                    error={errors[`lines.${index}.quantity`]}
                                >
                                    <QuantityInput
                                        id={`line-${line.key}-qty`}
                                        unit={line.article?.unit ?? 'u'}
                                        value={line.quantity}
                                        onValueChange={(value) =>
                                            setLine(line.key, {
                                                quantity: value,
                                            })
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id={`line-${line.key}-price`}
                                    label={t.fields.unitPrice}
                                    required
                                    error={errors[`lines.${index}.unit_price`]}
                                >
                                    <MoneyInput
                                        id={`line-${line.key}-price`}
                                        value={line.unit_price}
                                        onValueChange={(value) =>
                                            setLine(line.key, {
                                                unit_price: value,
                                            })
                                        }
                                    />
                                </FormField>
                                <div className="flex items-center justify-between gap-2 sm:grid sm:gap-2">
                                    <span className="text-sm text-muted-foreground sm:hidden">
                                        {t.fields.lineTotal}
                                    </span>
                                    <MoneyText
                                        value={previewLineTotal(
                                            line.quantity,
                                            line.unit_price,
                                        )}
                                        className="sm:flex sm:h-11 sm:items-center sm:justify-end"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-lg"
                                    className="justify-self-end text-destructive hover:text-destructive"
                                    disabled={form.data.lines.length === 1}
                                    onClick={() =>
                                        form.setData(
                                            'lines',
                                            form.data.lines.filter(
                                                (item) => item.key !== line.key,
                                            ),
                                        )
                                    }
                                    aria-label={t.removeLine}
                                >
                                    <Trash2 />
                                </Button>
                            </li>
                        ))}
                    </ul>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11"
                        onClick={() =>
                            form.setData('lines', [
                                ...form.data.lines,
                                newLine(),
                            ])
                        }
                    >
                        <Plus />
                        {t.addLine}
                    </Button>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="grid content-start gap-4 rounded-xl border bg-card p-4">
                        <FormField
                            id="purchase-document"
                            label={t.fields.document}
                            hint={t.fields.documentHint}
                            error={errors.document}
                        >
                            <FileInput
                                id="purchase-document"
                                value={form.data.document}
                                hasCurrent={purchase?.has_document}
                                onChange={(file) =>
                                    form.setData('document', file)
                                }
                            />
                        </FormField>
                        <FormField
                            id="purchase-notes"
                            label={t.fields.notes}
                            error={errors.notes}
                        >
                            <Textarea
                                id="purchase-notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </FormField>
                    </section>

                    <section className="grid content-start gap-4 rounded-xl border bg-card p-4">
                        <div className="flex min-h-11 items-center justify-between gap-4">
                            <div>
                                <label
                                    htmlFor="purchase-with-payment"
                                    className="font-semibold"
                                >
                                    {t.payment}
                                </label>
                                <p className="text-sm text-muted-foreground">
                                    {t.paymentHint}
                                </p>
                            </div>
                            <Switch
                                id="purchase-with-payment"
                                checked={withPayment}
                                onCheckedChange={setWithPayment}
                            />
                        </div>
                        {withPayment && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="purchase-paid"
                                    label={t.paidNow}
                                    error={errors.paid_amount}
                                >
                                    <MoneyInput
                                        id="purchase-paid"
                                        value={form.data.paid_amount}
                                        onValueChange={(value) =>
                                            form.setData('paid_amount', value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="purchase-paid-at"
                                    label={fr.payment.date}
                                    error={errors.paid_at}
                                >
                                    <Input
                                        id="purchase-paid-at"
                                        type="date"
                                        className="h-11"
                                        max={today}
                                        value={form.data.paid_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'paid_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="purchase-method"
                                    label={fr.payment.method}
                                    error={errors.payment_method_id}
                                >
                                    <Select
                                        value={form.data.payment_method_id}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'payment_method_id',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="purchase-method"
                                            className="h-11 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {paymentMethods.map((method) => (
                                                <SelectItem
                                                    key={method.id}
                                                    value={String(method.id)}
                                                >
                                                    {method.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField
                                    id="purchase-proof"
                                    label={fr.payment.proof}
                                    error={errors.proof}
                                >
                                    <FileInput
                                        id="purchase-proof"
                                        value={form.data.proof}
                                        onChange={(file) =>
                                            form.setData('proof', file)
                                        }
                                    />
                                </FormField>
                            </div>
                        )}
                    </section>
                </div>

                {/* Barre d'enregistrement collante : total toujours visible, au-dessus de la barre basse sur mobile. */}
                <div className="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 border-t bg-background/95 p-3 backdrop-blur md:sticky md:bottom-0 md:rounded-xl md:border md:p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-baseline justify-between gap-3 lg:block">
                            <span className="text-sm text-muted-foreground">
                                {t.total}
                            </span>
                            <MoneyText
                                value={total}
                                className="text-2xl lg:block"
                            />
                        </div>
                        <InputError message={errors.status} />
                        <div className="grid grid-cols-3 gap-2 lg:flex">
                            {(['draft', 'ordered', 'received'] as const).map(
                                (status) => (
                                    <Button
                                        key={status}
                                        type="button"
                                        variant={
                                            status === 'received'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        className="h-11 whitespace-normal"
                                        disabled={form.processing}
                                        onClick={() => submit(status)}
                                    >
                                        {form.processing &&
                                            target === status && <Spinner />}
                                        <span className="sm:hidden">
                                            {t.saveAsShort[status]}
                                        </span>
                                        <span className="hidden sm:inline">
                                            {t.saveAs[status]}
                                        </span>
                                    </Button>
                                ),
                            )}
                        </div>
                    </div>
                    <p className="mt-2 hidden text-xs text-muted-foreground md:block">
                        {t.saveAsHint}
                    </p>
                </div>
            </form>
        </>
    );
}
