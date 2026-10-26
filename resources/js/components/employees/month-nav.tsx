import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { fr } from '@/lib/i18n/fr';
import { shiftMonth } from '@/lib/period';

/** ‹ octobre 2026 › : change le mois affiché (paramètre ?period=). */
export function MonthNav({
    period,
    label,
    href,
}: {
    period: string;
    label: string;
    href: (period: string) => string;
}) {
    return (
        <div className="inline-flex items-center gap-1 rounded-lg border bg-card p-1">
            <Button asChild variant="ghost" size="icon-lg">
                <Link
                    href={href(shiftMonth(period, -1))}
                    preserveScroll
                    aria-label={fr.employees.previousMonth}
                >
                    <ChevronLeft />
                </Link>
            </Button>
            <span className="min-w-36 text-center font-medium first-letter:uppercase">
                {label}
            </span>
            <Button asChild variant="ghost" size="icon-lg">
                <Link
                    href={href(shiftMonth(period, 1))}
                    preserveScroll
                    aria-label={fr.employees.nextMonth}
                >
                    <ChevronRight />
                </Link>
            </Button>
        </div>
    );
}
