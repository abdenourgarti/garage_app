import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/app/form-field';
import { MoneyInput } from '@/components/app/money-input';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { toIsoDate } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { store as storeAdvance } from '@/routes/advances';

const t = fr.employees;

/** Saisie rapide d'une avance : employé, montant, date, mode. Remonté à chaque ouverture (key). */
export function AdvanceSheet({
    open,
    onOpenChange,
    employees,
    employeeId,
    paymentMethods,
    defaultPaymentMethodId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employees: { id: number; name: string }[];
    employeeId: number | null;
    paymentMethods: PaymentMethodOption[];
    defaultPaymentMethodId: number | null;
}) {
    const today = toIsoDate(new Date());
    const form = useForm({
        employee_id: employeeId ? String(employeeId) : '',
        amount: '',
        spent_at: today,
        payment_method_id: defaultPaymentMethodId
            ? String(defaultPaymentMethodId)
            : '',
    });

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <form
                    className="flex min-h-full flex-col"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(storeAdvance.url(), {
                            preserveScroll: true,
                            onSuccess: () => onOpenChange(false),
                        });
                    }}
                >
                    <SheetHeader>
                        <SheetTitle>{t.advanceTitle}</SheetTitle>
                        <SheetDescription>
                            {t.advanceDescription}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-4">
                        <FormField
                            id="advance-employee"
                            label={t.employee}
                            required
                            error={form.errors.employee_id}
                        >
                            <Select
                                value={form.data.employee_id}
                                onValueChange={(value) =>
                                    form.setData('employee_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="advance-employee"
                                    className="h-11 w-full"
                                >
                                    <SelectValue
                                        placeholder={fr.select.placeholder}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {employees.map((employee) => (
                                        <SelectItem
                                            key={employee.id}
                                            value={String(employee.id)}
                                        >
                                            {employee.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            id="advance-amount"
                            label={t.amount}
                            required
                            error={form.errors.amount}
                        >
                            <MoneyInput
                                id="advance-amount"
                                value={form.data.amount}
                                onValueChange={(value) =>
                                    form.setData('amount', value)
                                }
                                aria-invalid={Boolean(form.errors.amount)}
                            />
                        </FormField>
                        <div className="grid grid-cols-2 gap-3">
                            <FormField
                                id="advance-date"
                                label={t.date}
                                required
                                error={form.errors.spent_at}
                            >
                                <Input
                                    id="advance-date"
                                    type="date"
                                    className="h-11"
                                    max={today}
                                    value={form.data.spent_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'spent_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="advance-method"
                                label={t.method}
                                required
                                error={form.errors.payment_method_id}
                            >
                                <Select
                                    value={form.data.payment_method_id}
                                    onValueChange={(value) =>
                                        form.setData('payment_method_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="advance-method"
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
                        </div>
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
                            {t.saveAdvance}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
