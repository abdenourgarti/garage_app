import { router } from '@inertiajs/react';
import { Car, FileText, ListFilter, UserRound, Wrench } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { fr } from '@/lib/i18n/fr';
import { search as searchRoute } from '@/routes';
import { show as showClient } from '@/routes/clients';
import { show as showInvoice } from '@/routes/invoices';
import {
    index as ordersIndex,
    show as showOrder,
} from '@/routes/service-orders';
import { show as showVehicle } from '@/routes/vehicles';

type Hit = { id: number; label: string; description: string };
type Results = Record<'clients' | 'vehicles' | 'invoices' | 'orders', Hit[]>;

const t = fr.search;
const EMPTY: Results = { clients: [], vehicles: [], invoices: [], orders: [] };

const groups: {
    key: keyof Results;
    icon: LucideIcon;
    href: (id: number) => string;
}[] = [
    { key: 'clients', icon: UserRound, href: (id) => showClient.url(id) },
    { key: 'vehicles', icon: Car, href: (id) => showVehicle.url(id) },
    { key: 'invoices', icon: FileText, href: (id) => showInvoice.url(id) },
    { key: 'orders', icon: Wrench, href: (id) => showOrder.url(id) },
];

/**
 * Recherche globale (Ctrl+K) : une requête au serveur, résultats groupés, navigation au clavier (cmdk).
 * Plein écran sur téléphone. Remontée à chaque ouverture (key) : le champ repart vide.
 */
export function GlobalSearch({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Results>(EMPTY);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState('advanced');
    const term = query.trim();

    useEffect(() => {
        if (term.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);

            try {
                const response = await fetch(
                    searchRoute.url({ query: { q: term } }),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    const data = (await response.json()) as Results;
                    const first = groups.find(
                        (group) => data[group.key].length,
                    );

                    setResults(data);
                    // Entrée ouvre le premier résultat (sinon la recherche avancée).
                    setSelected(
                        first
                            ? `${first.key}-${data[first.key][0].id}`
                            : 'advanced',
                    );
                }
            } catch {
                // Requête remplacée par la frappe suivante.
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 200);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    const shown = term.length < 2 ? EMPTY : results;
    const total = groups.reduce(
        (sum, group) => sum + shown[group.key].length,
        0,
    );

    const go = (href: string) => {
        onOpenChange(false);
        router.visit(href);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="top-0 max-w-none translate-y-0 gap-0 overflow-hidden rounded-none p-0 max-sm:h-dvh sm:top-[12vh] sm:max-w-xl sm:rounded-xl">
                <DialogTitle className="sr-only">{t.title}</DialogTitle>
                <DialogDescription className="sr-only">
                    {t.keys}
                </DialogDescription>
                <Command
                    shouldFilter={false}
                    value={selected}
                    onValueChange={setSelected}
                    className="h-full rounded-none"
                >
                    <div className="flex items-center border-b pe-10 *:data-[slot=command-input-wrapper]:flex-1 *:data-[slot=command-input-wrapper]:border-0">
                        <CommandInput
                            value={query}
                            onValueChange={setQuery}
                            placeholder={t.placeholder}
                            className="h-12"
                            autoFocus
                        />
                        {loading && <Spinner className="ms-2" />}
                    </div>
                    <CommandList className="max-h-none flex-1 sm:max-h-[60vh]">
                        {term.length < 2 ? (
                            <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                {t.hint}
                            </p>
                        ) : (
                            !loading &&
                            total === 0 && (
                                <CommandEmpty>{t.empty}</CommandEmpty>
                            )
                        )}
                        {groups.map(
                            (group) =>
                                shown[group.key].length > 0 && (
                                    <CommandGroup
                                        key={group.key}
                                        heading={t.groups[group.key]}
                                    >
                                        {shown[group.key].map((hit) => (
                                            <CommandItem
                                                key={`${group.key}-${hit.id}`}
                                                value={`${group.key}-${hit.id}`}
                                                onSelect={() =>
                                                    go(group.href(hit.id))
                                                }
                                                className="min-h-12"
                                            >
                                                <group.icon />
                                                <span className="grid min-w-0">
                                                    <span className="truncate font-medium tabular-nums">
                                                        {hit.label}
                                                    </span>
                                                    <span className="truncate text-xs text-muted-foreground">
                                                        {hit.description}
                                                    </span>
                                                </span>
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                ),
                        )}
                        <CommandSeparator />
                        <CommandGroup>
                            <CommandItem
                                value="advanced"
                                onSelect={() =>
                                    go(
                                        ordersIndex.url({
                                            query: term ? { search: term } : {},
                                        }),
                                    )
                                }
                                className="min-h-12"
                            >
                                <ListFilter />
                                {t.advanced(term)}
                            </CommandItem>
                        </CommandGroup>
                    </CommandList>
                    <p className="hidden border-t px-4 py-2 text-xs text-muted-foreground sm:block">
                        {t.keys}
                    </p>
                </Command>
            </DialogContent>
        </Dialog>
    );
}
