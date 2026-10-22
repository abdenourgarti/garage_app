import { Head } from '@inertiajs/react';
import {
    Banknote,
    Car,
    Receipt,
    TrendingUp,
    Users,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ActiveToggle } from '@/components/app/active-toggle';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table';
import type { Column, Paginated } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { MoneyInput } from '@/components/app/money-input';
import { MoneyText } from '@/components/app/money-text';
import { PageHeader } from '@/components/app/page-header';
import { PeriodPicker } from '@/components/app/period-picker';
import { QuantityInput } from '@/components/app/quantity-input';
import { SearchSelect } from '@/components/app/search-select';
import type { SelectOption } from '@/components/app/search-select';
import { StatCard } from '@/components/app/stat-card';
import { StatusBadge } from '@/components/app/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatDate, formatQuantity } from '@/lib/format';
import { periodRange } from '@/lib/period';
import type { Period } from '@/lib/period';

// Page de démonstration interne (route déclarée seulement en local) : textes non traduits volontairement.

type DemoOrder = {
    id: number;
    number: string;
    client: string;
    plate: string;
    date: string;
    total: string;
    status: 'en_cours' | 'terminee';
};

const orders: Paginated<DemoOrder> = {
    data: [
        {
            id: 1,
            number: 'PR-2026-00042',
            client: 'Karim Benali',
            plate: '01234-118-16',
            date: '2026-10-22',
            total: '12500.00',
            status: 'en_cours',
        },
        {
            id: 2,
            number: 'PR-2026-00041',
            client: 'Samira Haddad',
            plate: '20871-114-31',
            date: '2026-10-21',
            total: '4800.50',
            status: 'terminee',
        },
        {
            id: 3,
            number: 'PR-2026-00040',
            client: 'Yacine Mansouri',
            plate: '15532-119-09',
            date: '2026-10-20',
            total: '38000.00',
            status: 'terminee',
        },
    ],
    current_page: 1,
    last_page: 3,
    total: 63,
    prev_page_url: null,
    next_page_url: '/dev/ui?page=2',
};

const columns: Column<DemoOrder>[] = [
    { key: 'number', header: 'Numéro', cell: (row) => row.number },
    {
        key: 'client',
        header: 'Client',
        cell: (row) => row.client,
        sortable: true,
    },
    { key: 'plate', header: 'Matricule', cell: (row) => row.plate },
    {
        key: 'date',
        header: 'Date',
        cell: (row) => formatDate(row.date),
        sortable: true,
        hideOnMobile: true,
    },
    {
        key: 'status',
        header: 'Statut',
        cell: (row) => <StatusBadge type="serviceOrder" status={row.status} />,
    },
    {
        key: 'total',
        header: 'Total',
        cell: (row) => <MoneyText value={row.total} />,
        align: 'end',
        sortable: true,
    },
];

const clients: SelectOption[] = [
    { value: '1', label: 'Karim Benali', description: '0550 12 34 56' },
    { value: '2', label: 'Samira Haddad', description: '0661 98 76 54' },
    { value: '3', label: 'Yacine Mansouri', description: '0770 11 22 33' },
];

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold">{title}</h2>
            {children}
        </section>
    );
}

function Gallery({ theme }: { theme: 'light' | 'dark' }) {
    const [amount, setAmount] = useState('12500');
    const [quantity, setQuantity] = useState('4.5');
    const [client, setClient] = useState<SelectOption | null>(null);
    const [active, setActive] = useState(true);
    const [period, setPeriod] = useState<Period>(periodRange('month'));

    return (
        <div
            className={`${theme} min-w-0 space-y-10 bg-background p-4 text-foreground md:p-8`}
        >
            <PageHeader
                title={theme === 'light' ? 'Mode clair' : 'Mode sombre'}
                description="Composants partagés de resources/js/components/app."
                actions={
                    <Button variant="highlight" className="h-11">
                        + Nouvelle prestation
                    </Button>
                }
            />

            <Section title="Couleurs">
                <div className="grid grid-cols-3 gap-2 text-xs sm:grid-cols-6">
                    {[
                        'primary',
                        'highlight',
                        'success',
                        'warning',
                        'destructive',
                        'muted',
                    ].map((token) => (
                        <div key={token} className="space-y-1">
                            <div
                                className="h-12 rounded-md border"
                                style={{ background: `var(--${token})` }}
                            />
                            <span>{token}</span>
                        </div>
                    ))}
                </div>
            </Section>

            <Section title="Boutons">
                <div className="flex flex-wrap gap-2">
                    <Button>Enregistrer</Button>
                    <Button variant="highlight">+ Nouvelle prestation</Button>
                    <Button variant="outline">Annuler</Button>
                    <Button variant="secondary">Secondaire</Button>
                    <Button variant="ghost">Discret</Button>
                    <Button variant="destructive">Annuler la facture</Button>
                </div>
            </Section>

            <Section title="StatCard (CountUp)">
                <div className="grid gap-3 sm:grid-cols-2">
                    <StatCard
                        label="Entrées du mois"
                        value={845000}
                        kind="money"
                        icon={Banknote}
                        hint="+12 % par rapport au mois dernier"
                    />
                    <StatCard
                        label="Dépenses du mois"
                        value={312500}
                        kind="money"
                        icon={Receipt}
                    />
                    <StatCard
                        label="Bénéfice"
                        value={532500}
                        kind="money"
                        icon={TrendingUp}
                        colorBySign
                    />
                    <StatCard
                        label="Prestations du jour"
                        value={7}
                        icon={Wrench}
                    />
                </div>
            </Section>

            <Section title="StatusBadge">
                <div className="flex flex-wrap gap-2">
                    <StatusBadge type="serviceOrder" status="en_cours" />
                    <StatusBadge type="serviceOrder" status="terminee" />
                    <StatusBadge type="invoice" status="impayee" />
                    <StatusBadge type="invoice" status="partielle" />
                    <StatusBadge type="invoice" status="payee" />
                    <StatusBadge type="invoice" status="annulee" />
                    <StatusBadge type="appointment" status="prevu" />
                    <StatusBadge type="stock" status="bas" />
                    <StatusBadge type="stock" status="rupture" />
                </div>
            </Section>

            <Section title="MoneyText">
                <div className="flex flex-wrap gap-6">
                    <MoneyText value="12500.00" />
                    <MoneyText value="12500.5" />
                    <MoneyText value={-3000} colorBySign />
                    <MoneyText value={532500} colorBySign />
                </div>
            </Section>

            <Section title="Saisie">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${theme}-amount`}>
                            Prix main-d'œuvre
                        </Label>
                        <MoneyInput
                            id={`${theme}-amount`}
                            value={amount}
                            onValueChange={setAmount}
                        />
                        <span className="text-xs text-muted-foreground">
                            Valeur envoyée : « {amount} »
                        </span>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${theme}-qty`}>Huile en vrac</Label>
                        <QuantityInput
                            id={`${theme}-qty`}
                            value={quantity}
                            onValueChange={setQuantity}
                        />
                        <span className="text-xs text-muted-foreground">
                            Affiché : {formatQuantity(quantity, 'L')}
                        </span>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${theme}-client`}>Client</Label>
                        <SearchSelect
                            id={`${theme}-client`}
                            value={client}
                            onChange={setClient}
                            options={clients}
                            onCreate={(query) =>
                                toast.success(
                                    `Création rapide du client « ${query} »`,
                                )
                            }
                            placeholder="Choisir un client"
                        />
                    </div>
                    <div className="grid gap-2">
                        <span className="text-sm font-medium">
                            Type de prestation
                        </span>
                        <ActiveToggle
                            checked={active}
                            onCheckedChange={setActive}
                        />
                    </div>
                </div>
            </Section>

            <Section title="PeriodPicker">
                <PeriodPicker value={period} onChange={setPeriod} />
                <p className="text-sm text-muted-foreground">
                    Du {formatDate(period.from)} au {formatDate(period.to)}
                </p>
            </Section>

            <Section title="ConfirmDialog et toasts">
                <div className="flex flex-wrap gap-2">
                    <ConfirmDialog
                        trigger={
                            <Button variant="destructive">
                                Annuler la facture
                            </Button>
                        }
                        title="Annuler la facture FA-2026-00012 ?"
                        description="Les paiements seront annulés et la prestation repassera « En cours »."
                        confirmLabel="Annuler la facture"
                        destructive
                        requireReason
                        onConfirm={(reason) =>
                            toast.success(`Facture annulée. Motif : ${reason}`)
                        }
                    />
                    <Button
                        variant="outline"
                        onClick={() => toast.success('Prestation enregistrée.')}
                    >
                        Toast succès
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() =>
                            toast.error('Stock insuffisant pour cet article.')
                        }
                    >
                        Toast erreur
                    </Button>
                </div>
            </Section>

            <Section title="DataTable (cartes sous 768 px)">
                <DataTable
                    rows={orders}
                    columns={columns}
                    rowKey={(row) => row.id}
                    rowHref={() => '#'}
                    searchPlaceholder="Client, matricule, numéro…"
                    empty={null}
                />
            </Section>

            <Section title="EmptyState">
                <EmptyState
                    icon={Users}
                    title="Aucun client"
                    description="Ajoutez votre premier client pour créer une prestation."
                    action={
                        <Button className="h-11">
                            <Car />
                            Ajouter le premier client
                        </Button>
                    }
                />
            </Section>
        </div>
    );
}

export default function DevUi() {
    return (
        <>
            <Head title="Composants" />
            <div className="grid min-h-svh 2xl:grid-cols-2">
                <Gallery theme="light" />
                <Gallery theme="dark" />
            </div>
        </>
    );
}
