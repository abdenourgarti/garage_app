import { useSyncExternalStore } from 'react';

/**
 * Règles d'animation (docs/04-design-system.md §6) : guider, jamais ralentir.
 * Durées 150–300 ms, easing power2.out, rien si l'utilisateur réduit les animations.
 */
export const duration = {
    fast: 0.15,
    base: 0.2,
    slow: 0.3,
} as const;

export const ease = 'power2.out';

const query = '(prefers-reduced-motion: reduce)';

const mql =
    typeof window === 'undefined' ? undefined : window.matchMedia(query);

function subscribe(callback: () => void): () => void {
    mql?.addEventListener('change', callback);

    return () => mql?.removeEventListener('change', callback);
}

export function prefersReducedMotion(): boolean {
    return mql?.matches ?? false;
}

export function useReducedMotion(): boolean {
    return useSyncExternalStore(subscribe, prefersReducedMotion, () => true);
}
