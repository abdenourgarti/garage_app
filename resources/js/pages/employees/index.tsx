import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import { ActiveToggle } from '@/components/app/active-toggle';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { FormField } from '@/components/app/form-field';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { fr } from '@/lib/i18n/fr';

type EmployeeRow = {
    id: number;
    name: string;
    position: string | null;
    phone: string | null;
    is_active: boolean;
};

const t = fr.employees;

function EmployeeSheet({
    employee,
    open,
    onOpenChange,
}: {
    employee: EmployeeRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        name: employee?.name ?? '',
        position: employee?.position ?? '',
        phone: employee?.phone ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform(
            (data) =>
                ({
                    name: data.name,
                    position: data.position || null,
                    phone: data.phone || null,
                }) as never,
        );
        form.submit(
            employee
                ? EmployeeController.update(employee.id)
                : EmployeeController.store(),
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-md">
                <form onSubmit={submit} className="flex min-h-full flex-col">
                    <SheetHeader>
                        <SheetTitle>{employee ? t.edit : t.create}</SheetTitle>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-4">
                        <FormField
                            id="employee-name"
                            label={t.name}
                            required
                            error={form.errors.name}
                        >
                            <Input
                                id="employee-name"
                                className="h-11"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.name)}
                            />
                        </FormField>
                        <FormField
                            id="employee-position"
                            label={t.position}
                            error={form.errors.position}
                        >
                            <Input
                                id="employee-position"
                                className="h-11"
                                placeholder={t.positionPlaceholder}
                                value={form.data.position}
                                onChange={(event) =>
                                    form.setData('position', event.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="employee-phone"
                            label={t.phone}
                            error={form.errors.phone}
                        >
                            <Input
                                id="employee-phone"
                                type="tel"
                                inputMode="tel"
                                className="h-11"
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
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

export default function EmployeesIndex({
    rows,
}: {
    rows: Paginated<EmployeeRow>;
}) {
    const [editing, setEditing] = useState<EmployeeRow | 'new' | null>(null);
    const [openCount, setOpenCount] = useState(0);

    const open = (target: EmployeeRow | 'new') => {
        setOpenCount((count) => count + 1);
        setEditing(target);
    };

    const columns: Column<EmployeeRow>[] = [
        { key: 'name', header: t.name, cell: (row) => row.name },
        {
            key: 'position',
            header: t.position,
            cell: (row) => row.position ?? '—',
        },
        {
            key: 'phone',
            header: t.phone,
            cell: (row) => (
                <span className="tabular-nums">{row.phone ?? '—'}</span>
            ),
        },
        {
            key: 'status',
            header: t.status,
            cell: (row) => (
                <ActiveToggle
                    checked={row.is_active}
                    onCheckedChange={() =>
                        router.patch(
                            EmployeeController.toggle.url(row.id),
                            {},
                            { preserveScroll: true },
                        )
                    }
                />
            ),
        },
        {
            key: 'actions',
            header: t.actions,
            align: 'end',
            cell: (row) => (
                <Button
                    variant="ghost"
                    size="icon-lg"
                    onClick={() => open(row)}
                    aria-label={`${t.edit} ${row.name}`}
                >
                    <Pencil />
                </Button>
            ),
        },
    ];

    const addButton = (
        <Button className="h-11" onClick={() => open('new')}>
            <Plus />
            {t.add}
        </Button>
    );

    return (
        <>
            <Head title={t.title} />
            <div className="space-y-6 p-4 md:p-6">
                <PageHeader
                    title={t.title}
                    description={t.description}
                    actions={addButton}
                />
                <DataTable
                    rows={rows}
                    columns={columns}
                    rowKey={(row) => row.id}
                    searchPlaceholder={t.search}
                    empty={
                        <EmptyState
                            icon={Users}
                            title={t.emptyTitle}
                            description={t.emptyText}
                            action={addButton}
                        />
                    }
                />
            </div>
            <EmployeeSheet
                key={openCount}
                employee={editing === 'new' ? null : editing}
                open={editing !== null}
                onOpenChange={(value) => !value && setEditing(null)}
            />
        </>
    );
}
