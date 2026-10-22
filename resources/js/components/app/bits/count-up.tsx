import { useGSAP } from '@gsap/react';
import gsap from 'gsap';
import { useRef } from 'react';
import { ease, prefersReducedMotion } from '@/lib/animations';
import { cn } from '@/lib/utils';

gsap.registerPlugin(useGSAP);

type Props = {
    to: number;
    from?: number;
    /** Secondes ; 0,4 s maximum (docs/04-design-system.md §6). */
    duration?: number;
    format?: (value: number) => string;
    className?: string;
};

/**
 * Compteur animé, adapté de React Bits « CountUp » (TS + Tailwind) :
 * l'original dépend de `motion`, réécrit ici avec GSAP déjà présent.
 * Le texte final est rendu tout de suite (lecteurs d'écran, animations réduites).
 */
export function CountUp({
    to,
    from = 0,
    duration = 0.4,
    format = (value) => Math.round(value).toLocaleString('fr-DZ'),
    className,
}: Props) {
    const ref = useRef<HTMLSpanElement>(null);

    useGSAP(
        () => {
            const element = ref.current;

            if (!element || prefersReducedMotion()) {
                return;
            }

            const counter = { value: from };

            gsap.to(counter, {
                value: to,
                duration: Math.min(duration, 0.4),
                ease,
                onUpdate: () => {
                    element.textContent = format(counter.value);
                },
                onComplete: () => {
                    element.textContent = format(to);
                },
            });
        },
        { dependencies: [to, from], scope: ref },
    );

    return (
        <span ref={ref} className={cn('tabular-nums', className)}>
            {format(to)}
        </span>
    );
}
