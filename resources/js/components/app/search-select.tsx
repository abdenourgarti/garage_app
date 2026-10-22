import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
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
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { fr } from '@/lib/i18n/fr';
import { cn } from '@/lib/utils';

export type SelectOption = {
    value: string;
    label: string;
    /** Ligne secondaire : téléphone, matricule, référence… */
    description?: string;
};

type Props = {
    id?: string;
    value: SelectOption | null;
    onChange: (option: SelectOption | null) => void;
    /** Liste fixe, filtrée localement… */
    options?: SelectOption[];
    /** …ou recherche serveur, lancée dès 2 caractères. */
    loadOptions?: (query: string) => Promise<SelectOption[]>;
    /** Affiche « + Ajouter » : jamais de cul-de-sac (docs/04-design-system.md §1.6). */
    onCreate?: (query: string) => void;
    placeholder?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
    className?: string;
};

const MIN_QUERY = 2;

export function SearchSelect({
    id,
    value,
    onChange,
    options = [],
    loadOptions,
    onCreate,
    placeholder = fr.select.placeholder,
    disabled,
    className,
    ...props
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [remote, setRemote] = useState<SelectOption[]>([]);
    const [loading, setLoading] = useState(false);
    // Ref : une fonction passée en ligne par la page ne doit pas relancer la recherche à chaque rendu.
    const loadRef = useRef(loadOptions);
    const hasLoader = loadOptions !== undefined;

    useEffect(() => {
        loadRef.current = loadOptions;
    });

    useEffect(() => {
        const load = loadRef.current;

        if (!load || query.trim().length < MIN_QUERY) {
            return;
        }

        let cancelled = false;
        const timer = window.setTimeout(() => {
            setLoading(true);
            void load(query.trim())
                .then((results) => !cancelled && setRemote(results))
                .catch(() => !cancelled && setRemote([]))
                .finally(() => !cancelled && setLoading(false));
        }, 250);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [hasLoader, query]);

    const items = loadOptions
        ? query.trim().length >= MIN_QUERY
            ? remote
            : []
        : options;

    const select = (option: SelectOption | null) => {
        onChange(option);
        setOpen(false);
        setQuery('');
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={props['aria-invalid']}
                    disabled={disabled}
                    className={cn(
                        'h-11 w-full justify-between px-3 font-normal',
                        className,
                    )}
                >
                    <span
                        className={cn(
                            'truncate',
                            !value && 'text-muted-foreground',
                        )}
                    >
                        {value?.label ?? placeholder}
                    </span>
                    <ChevronsUpDown className="opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-(--radix-popover-trigger-width) min-w-64 p-0"
                align="start"
            >
                <Command shouldFilter={!loadOptions}>
                    <CommandInput
                        value={query}
                        onValueChange={setQuery}
                        placeholder={fr.select.search}
                    />
                    <CommandList>
                        {loading ? (
                            <div className="flex justify-center py-6">
                                <Spinner />
                            </div>
                        ) : (
                            <CommandEmpty>{fr.common.noResult}</CommandEmpty>
                        )}
                        <CommandGroup>
                            {items.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={`${option.label} ${option.description ?? ''} ${option.value}`}
                                    onSelect={() => select(option)}
                                >
                                    <Check
                                        className={cn(
                                            'shrink-0',
                                            value?.value === option.value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <div className="grid min-w-0">
                                        <span className="truncate">
                                            {option.label}
                                        </span>
                                        {option.description && (
                                            <span className="truncate text-xs text-muted-foreground">
                                                {option.description}
                                            </span>
                                        )}
                                    </div>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                        {onCreate && (
                            <>
                                <CommandSeparator />
                                <CommandGroup>
                                    <CommandItem
                                        forceMount
                                        value="__create__"
                                        onSelect={() => {
                                            setOpen(false);
                                            onCreate(query.trim());
                                            setQuery('');
                                        }}
                                        className="font-medium text-primary"
                                    >
                                        <Plus className="text-primary" />
                                        {fr.select.addNew(query.trim())}
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
