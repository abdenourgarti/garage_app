import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type NavItem = {
    title: string;
    /** Libellé court pour la barre basse mobile. */
    shortTitle?: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    /** Autres chemins où l'entrée est active (ex. /vehicles pour Clients). */
    activeFor?: string[];
};
