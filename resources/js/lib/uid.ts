let last = 0;

/**
 * Identifiant unique côté navigateur pour les lignes ajoutées dans un formulaire (clé React et repérage).
 * Un seul compteur pour toute l'application : deux compteurs distincts produiraient des doublons.
 */
export function uid(): number {
    last += 1;

    return last;
}
