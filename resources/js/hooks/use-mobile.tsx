import { useSyncExternalStore } from 'react';

/** Points de rupture de docs/04-design-system.md §3. */
const MOBILE_QUERY = '(max-width: 767px)';
const DESKTOP_QUERY = '(min-width: 1280px)';

function useMediaQuery(query: string, serverValue: boolean): boolean {
    return useSyncExternalStore(
        (callback) => {
            const mql = window.matchMedia(query);
            mql.addEventListener('change', callback);

            return () => mql.removeEventListener('change', callback);
        },
        () => window.matchMedia(query).matches,
        () => serverValue,
    );
}

/** Moins de 768 px : barre basse, pas de sidebar. */
export function useIsMobile(): boolean {
    return useMediaQuery(MOBILE_QUERY, false);
}

/** 1280 px et plus : sidebar dépliée ; en dessous, repliée en icônes. */
export function useIsDesktop(): boolean {
    return useMediaQuery(DESKTOP_QUERY, true);
}
