import { Head, Link, router } from '@inertiajs/react';
import { ListPlus, Lock, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ReferenceController from '@/actions/App/Http/Controllers/Admin/ReferenceController';
import { cellText, referenceFields } from '@/components/admin/reference-fields';
import type {
    Field,
    ReferenceRow,
    ReferenceTypeValue,
} from '@/components/admin/reference-fields';
import { ReferenceFormSheet } from '@/components/admin/reference-form-sheet';
import { ActiveToggle } from '@/components/app/active-toggle';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyText } from '@/components/app/money-text';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatQuantity } from '@/lib/format';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';
import { index as referencesIndex } from '@/routes/admin/references';

type Props = {
    type: ReferenceTypeValue;
    types: { value: ReferenceTypeValue; label: string }[];
    rows: Paginated<ReferenceRow>;
    filters: { search: string; brand: number | null };
    brands: { id: number; name: string; is_active: boolean }[];
};

function display(field: Field, row: ReferenceRow) {
    const value = row[field.name];

    switch (field.kind) {
        case 'money':
            return <MoneyText value={value as string} />;
        case 'liters':
            return value ? formatQuantity(value as string, 'L') : '—';
        case 'km':
            return value ? formatQuantity(value as number, 'km') : '—';
        case 'boolean':
            return value ? fr.admin.yes : fr.admin.no;
        case 'url':
            return value ? (
                <a
                    href={cellText(value)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-primary underline-offset-4 hover:underline"
                >
                    {fr.admin.fields.openMap}
                </a>
            ) : (
                '—'
            );
        case 'brand':
            return row.brand?.name ?? '—';
        default:
            return cellText(value) || '—';
    }
}

const ALL_BRANDS = 'all';

export default function References({
    type,
    types,
    rows,
    filters,
    brands,
}: Props) {
    const [editing, setEditing] = useState<ReferenceRow | 'new' | null>(null);
    const [openCount, setOpenCount] = useState(0);
    const [deleting, setDeleting] = useState<ReferenceRow | null>(null);
    const typeLabel = types.find((item) => item.value === type)?.label ?? '';

    const open = (target: ReferenceRow | 'new') => {
        setOpenCount((count) => count + 1);
        setEditing(target);
    };

    const fieldColumns: Column<ReferenceRow>[] = referenceFields[type]
        .filter((field) => field.kind !== 'textarea')
        .map((field) => ({
            key: field.name,
            header: field.label,
            cell: (row) =>
                field.name === 'name' && row.system_key ? (
                    <span className="inline-flex items-center gap-2">
                        {row.name}
                        <span
                            className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            title={fr.admin.systemHint}
                        >
                            <Lock className="size-3" aria-hidden="true" />
                            {fr.admin.system}
                        </span>
                    </span>
                ) : (
                    display(field, row)
                ),
            align: ['money', 'liters', 'km'].includes(field.kind)
                ? 'end'
                : 'start',
        }));

    const columns: Column<ReferenceRow>[] = [
        ...fieldColumns,
        {
            key: 'is_active',
            header: fr.admin.status,
            cell: (row) =>
                row.system_key ? (
                    <span className="text-sm text-success">
                        {fr.common.active}
                    </span>
                ) : (
                    <ActiveToggle
                        checked={row.is_active}
                        onCheckedChange={() =>
                            router.patch(
                                ReferenceController.toggle.url({
                                    type,
                                    id: row.id,
                                }),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    />
                ),
        },
        {
            key: 'actions',
            header: fr.admin.actions,
            align: 'end',
            cell: (row) =>
                row.system_key ? null : (
                    <div className="flex justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="icon-lg"
                            onClick={() => open(row)}
                            aria-label={`${fr.admin.edit} ${row.name}`}
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-lg"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeleting(row)}
                            aria-label={`${fr.admin.delete} ${row.name}`}
                        >
                            <Trash2 />
                        </Button>
                    </div>
                ),
        },
    ];

    const brandFilter = type === 'vehicle-models' && (
        <Select
            value={filters.brand ? String(filters.brand) : ALL_BRANDS}
            onValueChange={(value) =>
                router.get(
                    referencesIndex.url({ type }),
                    {
                        search: filters.search || undefined,
                        brand: value === ALL_BRANDS ? undefined : value,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                )
            }
        >
            <SelectTrigger
                className="h-11 w-full sm:w-56"
                aria-label={fr.admin.fields.brand}
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL_BRANDS}>{fr.admin.allBrands}</SelectItem>
                {brands.map((brand) => (
                    <SelectItem key={brand.id} value={String(brand.id)}>
                        {brand.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <>
            <Head title={`${typeLabel} - ${fr.admin.references}`} />

            <div className="flex flex-col gap-4 lg:flex-row lg:gap-8">
                {/* Smartphone : liste déroulante ; tablette et PC : liste verticale */}
                <div className="lg:hidden">
                    <Select
                        value={type}
                        onValueChange={(value) =>
                            router.visit(referencesIndex.url({ type: value }))
                        }
                    >
                        <SelectTrigger
                            className="h-11 w-full sm:w-72"
                            aria-label={fr.admin.referenceList}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {types.map((item) => (
                                <SelectItem key={item.value} value={item.value}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <nav
                    aria-label={fr.admin.referenceList}
                    className="hidden w-56 shrink-0 flex-col gap-1 lg:flex"
                >
                    {types.map((item) => (
                        <Link
                            key={item.value}
                            href={referencesIndex({ type: item.value })}
                            className={cn(
                                'flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground',
                                item.value === type &&
                                    'bg-accent text-foreground',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                <section className="min-w-0 flex-1 space-y-4">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold">{typeLabel}</h2>
                        <Button className="h-11" onClick={() => open('new')}>
                            <Plus />
                            {fr.admin.add}
                        </Button>
                    </div>

                    <DataTable
                        key={type}
                        rows={rows}
                        columns={columns}
                        rowKey={(row) => row.id}
                        searchPlaceholder={fr.common.search}
                        filters={brandFilter}
                        empty={
                            <EmptyState
                                icon={ListPlus}
                                title={fr.admin.emptyTitle}
                                description={fr.admin.emptyText}
                                action={
                                    <Button
                                        className="h-11"
                                        onClick={() => open('new')}
                                    >
                                        <Plus />
                                        {fr.admin.add}
                                    </Button>
                                }
                            />
                        }
                    />
                </section>
            </div>

            <ReferenceFormSheet
                key={`${type}-${openCount}`}
                type={type}
                typeLabel={typeLabel}
                editing={editing}
                onClose={() => setEditing(null)}
                brands={brands}
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(isOpen) => !isOpen && setDeleting(null)}
                title={fr.admin.deleteTitle(deleting?.name ?? '')}
                description={fr.admin.deleteText}
                confirmLabel={fr.admin.delete}
                destructive
                onConfirm={() => {
                    if (deleting) {
                        router.delete(
                            ReferenceController.destroy.url({
                                type,
                                id: deleting.id,
                            }),
                            {
                                preserveScroll: true,
                                onFinish: () => setDeleting(null),
                            },
                        );
                    }
                }}
            />
        </>
    );
}
