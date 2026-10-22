import { usePage } from '@inertiajs/react';
import {
    Banknote,
    CalendarClock,
    Car,
    FileText,
    House,
    Package,
    Settings2,
    Users,
    Wrench,
} from 'lucide-react';
import { fr } from '@/lib/i18n/fr';
import { dashboard } from '@/routes';
import { index as admin } from '@/routes/admin';
import { index as appointments } from '@/routes/appointments';
import { index as cash } from '@/routes/cash';
import { index as clients } from '@/routes/clients';
import { index as employees } from '@/routes/employees';
import { index as invoices } from '@/routes/invoices';
import { index as serviceOrders } from '@/routes/service-orders';
import { index as stock } from '@/routes/stock';
import type { NavItem } from '@/types';

/** Menu filtré par rôle (prop partagée auth.can) ; les 4 premières entrées vont dans la barre basse mobile. */
export function useNavigation(): {
    items: NavItem[];
    bottom: NavItem[];
    more: NavItem[];
} {
    const { can } = usePage().props.auth;

    const bottom: NavItem[] = [
        { title: fr.nav.dashboard, href: dashboard(), icon: House },
        { title: fr.nav.serviceOrders, href: serviceOrders(), icon: Wrench },
        {
            title: fr.nav.clients,
            shortTitle: fr.nav.clientsShort,
            href: clients(),
            icon: Car,
        },
        {
            title: fr.nav.stock,
            shortTitle: fr.nav.stockShort,
            href: stock(),
            icon: Package,
        },
    ];

    const more: NavItem[] = [
        {
            title: fr.nav.appointments,
            href: appointments(),
            icon: CalendarClock,
        },
        { title: fr.nav.invoices, href: invoices(), icon: FileText },
        ...(can.cash
            ? [{ title: fr.nav.cash, href: cash(), icon: Banknote }]
            : []),
        ...(can.employees
            ? [{ title: fr.nav.employees, href: employees(), icon: Users }]
            : []),
        ...(can.references
            ? [{ title: fr.nav.admin, href: admin(), icon: Settings2 }]
            : []),
    ];

    const [home, orders, ...rest] = bottom;

    // Ordre de la sidebar : les rendez-vous juste après les prestations.
    return {
        items: [home, orders, more[0], ...rest, ...more.slice(1)],
        bottom,
        more,
    };
}
