import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Search,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';

export type Column<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    /** Montants et quantités : 'end'. */
    align?: 'start' | 'end';
    /** Tri serveur via ?sort=key&direction=asc|desc. */
    sortable?: boolean;
    /** Masquer la colonne dans les cartes mobiles. */
    hideOnMobile?: boolean;
    className?: string;
};

/** Forme JSON d'un paginator Laravel (->paginate(25)). */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props<T> = {
    rows: Paginated<T>;
    columns: Column<T>[];
    rowKey: (row: T) => string | number;
    /** Lien de la ligne (fiche) : toute la ligne / carte devient cliquable. */
    rowHref?: (row: T) => string;
    /** Champ de recherche lié à ?search= (filtres partageables, retour arrière préservé). */
    searchPlaceholder?: string;
    /** Filtres supplémentaires affichés à côté de la recherche. */
    filters?: ReactNode;
    empty: ReactNode;
};

function currentQuery(url: string): Record<string, string> {
    return Object.fromEntries(
        new URL(url, window.location.origin).searchParams,
    );
}

/** Tableau serveur sur PC, liste de cartes sous 768 px (docs/04-design-system.md §4). */
export function DataTable<T>({
    rows,
    columns,
    rowKey,
    rowHref,
    searchPlaceholder,
    filters,
    empty,
}: Props<T>) {
    const { url } = usePage();
    const query = currentQuery(url);
    const [search, setSearch] = useState(query.search ?? '');
    const searchTimer = useRef<number | undefined>(undefined);

    const visit = (params: Record<string, string | undefined>) => {
        router.get(
            url.split('?')[0],
            { ...query, page: undefined, ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sortBy = (key: string) => {
        const direction =
            query.sort === key && query.direction === 'asc' ? 'desc' : 'asc';
        visit({ sort: key, direction });
    };

    const [titleColumn, ...detailColumns] = columns;

    return (
        <div className="space-y-4">
            {(searchPlaceholder || filters) && (
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    {searchPlaceholder && (
                        <div className="relative sm:max-w-sm sm:flex-1">
                            <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-muted-foreground" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setSearch(value);
                                    window.clearTimeout(searchTimer.current);
                                    searchTimer.current = window.setTimeout(
                                        () =>
                                            visit({
                                                search: value || undefined,
                                            }),
                                        300,
                                    );
                                }}
                                placeholder={searchPlaceholder}
                                aria-label={fr.common.search}
                                className="h-11 ps-9"
                            />
                        </div>
                    )}
                    {filters}
                </div>
            )}

            {rows.data.length === 0 ? (
                empty
            ) : (
                <>
                    {/* PC et tablette */}
                    <div className="hidden overflow-hidden rounded-xl border bg-card md:block">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    {columns.map((column) => {
                                        const sorted =
                                            query.sort === column.key
                                                ? query.direction
                                                : undefined;
                                        const SortIcon =
                                            sorted === 'asc'
                                                ? ArrowUp
                                                : sorted === 'desc'
                                                  ? ArrowDown
                                                  : ArrowUpDown;

                                        return (
                                            <TableHead
                                                key={column.key}
                                                aria-sort={
                                                    sorted === 'asc'
                                                        ? 'ascending'
                                                        : sorted === 'desc'
                                                          ? 'descending'
                                                          : undefined
                                                }
                                                className={cn(
                                                    column.align === 'end' &&
                                                        'text-end',
                                                    column.className,
                                                )}
                                            >
                                                {column.sortable ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            sortBy(column.key)
                                                        }
                                                        className={cn(
                                                            'inline-flex min-h-11 items-center gap-1 hover:text-foreground',
                                                            column.align ===
                                                                'end' &&
                                                                'flex-row-reverse',
                                                        )}
                                                        aria-label={fr.table.sortBy(
                                                            column.header,
                                                        )}
                                                    >
                                                        {column.header}
                                                        <SortIcon
                                                            className={cn(
                                                                'size-3.5',
                                                                !sorted &&
                                                                    'opacity-40',
                                                            )}
                                                        />
                                                    </button>
                                                ) : (
                                                    column.header
                                                )}
                                            </TableHead>
                                        );
                                    })}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.data.map((row) => (
                                    <TableRow key={rowKey(row)}>
                                        {columns.map((column, index) => (
                                            <TableCell
                                                key={column.key}
                                                className={cn(
                                                    column.align === 'end' &&
                                                        'text-end',
                                                    column.className,
                                                )}
                                            >
                                                {index === 0 && rowHref ? (
                                                    <Link
                                                        href={rowHref(row)}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {column.cell(row)}
                                                    </Link>
                                                ) : (
                                                    column.cell(row)
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Smartphone : une carte par ligne */}
                    <ul className="grid gap-2 md:hidden">
                        {rows.data.map((row) => {
                            const content = (
                                <>
                                    <div className="font-semibold">
                                        {titleColumn.cell(row)}
                                    </div>
                                    <dl className="mt-2 grid grid-cols-2 gap-x-3 gap-y-1.5 text-sm">
                                        {detailColumns
                                            .filter(
                                                (column) =>
                                                    !column.hideOnMobile,
                                            )
                                            .map((column) => (
                                                <div
                                                    key={column.key}
                                                    className="contents"
                                                >
                                                    <dt className="text-muted-foreground">
                                                        {column.header}
                                                    </dt>
                                                    <dd className="text-end">
                                                        {column.cell(row)}
                                                    </dd>
                                                </div>
                                            ))}
                                    </dl>
                                </>
                            );

                            return (
                                <li key={rowKey(row)}>
                                    {rowHref ? (
                                        <Link
                                            href={rowHref(row)}
                                            className="block rounded-xl border bg-card p-4 active:bg-accent"
                                        >
                                            {content}
                                        </Link>
                                    ) : (
                                        <div className="rounded-xl border bg-card p-4">
                                            {content}
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </>
            )}

            {rows.last_page > 1 && (
                <nav
                    className="flex items-center justify-between gap-2"
                    aria-label={fr.table.page(
                        rows.current_page,
                        rows.last_page,
                    )}
                >
                    <PageLink href={rows.prev_page_url}>
                        <ChevronLeft />
                        {fr.table.previous}
                    </PageLink>
                    <span className="text-center text-sm text-muted-foreground">
                        {fr.table.page(rows.current_page, rows.last_page)} (
                        {fr.table.results(rows.total)})
                    </span>
                    <PageLink href={rows.next_page_url}>
                        {fr.table.next}
                        <ChevronRight />
                    </PageLink>
                </nav>
            )}
        </div>
    );
}

function PageLink({
    href,
    children,
}: {
    href: string | null;
    children: ReactNode;
}) {
    if (!href) {
        return (
            <Button variant="outline" className="h-11" disabled>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" className="h-11" asChild>
            <Link href={href} preserveScroll preserveState>
                {children}
            </Link>
        </Button>
    );
}
