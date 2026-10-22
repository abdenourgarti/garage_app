/**
 * Tous les textes de l'interface (CLAUDE.md, règle 2).
 * L'arabe viendra plus tard : garder des clés stables et des phrases complètes.
 */
export const fr = {
    common: {
        save: 'Enregistrer',
        cancel: 'Annuler',
        confirm: 'Confirmer',
        close: 'Fermer',
        add: 'Ajouter',
        search: 'Rechercher',
        searchPlaceholder: 'Client, téléphone, matricule…',
        loading: 'Chargement…',
        saved: 'Enregistré.',
        active: 'Actif',
        inactive: 'Inactif',
        required: 'obligatoire',
        moreInfo: "Plus d'informations",
        noResult: 'Aucun résultat.',
        comingSoon: 'Bientôt disponible',
        comingSoonText:
            'Cet écran arrive dans une prochaine version. Le menu est déjà prêt.',
        backToHome: "Retour à l'accueil",
    },

    nav: {
        dashboard: 'Accueil',
        serviceOrders: 'Prestations',
        clients: 'Clients et véhicules',
        clientsShort: 'Clients',
        appointments: 'Rendez-vous',
        invoices: 'Factures',
        stock: 'Stock et achats',
        stockShort: 'Stock',
        cash: 'Caisse',
        employees: 'Employés',
        admin: 'Paramètres',
        more: 'Plus',
        mainMenu: 'Menu principal',
        newServiceOrder: 'Nouvelle prestation',
        newServiceOrderShort: 'Prestation',
        search: 'Rechercher',
    },

    admin: {
        title: 'Paramètres',
        description:
            "Les informations du garage et les listes proposées dans l'application.",
        garage: 'Garage',
        references: 'Référentiels',
        referenceList: 'Liste',
        add: 'Ajouter',
        edit: 'Modifier',
        delete: 'Supprimer',
        newItem: 'Nouvel élément',
        editItem: "Modifier l'élément",
        system: 'Système',
        systemHint: 'Utilisée par le logiciel : non modifiable.',
        allBrands: 'Toutes les marques',
        status: 'État',
        actions: 'Actions',
        yes: 'Oui',
        no: 'Non',
        emptyTitle: 'Aucun élément',
        emptyText: 'Ajoutez le premier élément de cette liste.',
        deleteTitle: (name: string) => `Supprimer « ${name} » ?`,
        deleteText:
            'Si cet élément est déjà utilisé, la suppression sera refusée : désactivez-le plutôt.',
        fields: {
            code: 'Code',
            name: 'Nom',
            brand: 'Marque',
            oilCapacity: "Capacité d'huile moteur",
            oilCapacityHint:
                'Optionnel : proposée automatiquement sur les vidanges.',
            laborPrice: "Prix de main-d'œuvre par défaut",
            requiresMileage: 'Kilométrage demandé',
            requiresMileageHint:
                'Le kilométrage du véhicule sera obligatoire pour ce type de prestation.',
            reminderKm: 'Rappel conseillé',
            reminderKmHint:
                'Prochaine visite conseillée après ce nombre de kilomètres. Vide : pas de rappel.',
            phone: 'Téléphone',
            email: 'E-mail',
            address: 'Adresse',
            mapsUrl: 'Lien Google Maps',
            mapsUrlHint: 'Copiez le lien « Partager » de Google Maps.',
            openMap: 'Ouvrir la carte',
            notes: 'Remarque',
            isCash: 'Paiement en espèces',
            isCashHint: 'Compte dans le solde de caisse du jour.',
        },
        garageForm: {
            name: 'Nom du garage',
            address: 'Adresse',
            phone: 'Téléphone',
            logo: 'Logo',
            logoHint:
                'PNG, JPG ou WebP, 2 Mo maximum. Imprimé sur les factures.',
            removeLogo: 'Retirer le logo',
            reminderThreshold: 'Seuil de rappel de vidange',
            reminderThresholdHint:
                'Un véhicule apparaît dans les rappels quand il lui reste moins de ce kilométrage avant sa prochaine vidange.',
        },
    },

    clients: {
        title: 'Clients et véhicules',
        description:
            'Retrouvez un client par son téléphone, son nom ou un matricule.',
        tabClients: 'Clients',
        tabVehicles: 'Véhicules',
        newClient: 'Nouveau client',
        searchClients: 'Téléphone, nom, prénom ou matricule…',
        searchVehicles: 'Matricule, châssis, marque, modèle ou propriétaire…',
        allWilayas: 'Toutes les wilayas',
        allBrands: 'Toutes les marques',
        inactive: 'Inactif',
        emptyTitle: 'Aucun client',
        emptyText: 'Ajoutez votre premier client pour commencer.',
        emptyVehiclesTitle: 'Aucun véhicule',
        emptyVehiclesText:
            'Les véhicules s’ajoutent depuis la fiche d’un client.',
        fields: {
            lastName: 'Nom',
            firstName: 'Prénom',
            phone: 'Téléphone',
            wilaya: 'Wilaya',
            notes: 'Remarque',
        },
        name: 'Nom',
        vehicles: 'Véhicules',
        vehicleCount: (count: number) =>
            count === 0
                ? 'Aucun véhicule'
                : count === 1
                  ? '1 véhicule'
                  : `${count} véhicules`,
        tabs: {
            vehicles: 'Véhicules',
            serviceOrders: 'Prestations',
            invoices: 'Factures',
            credit: 'Crédit',
        },
        creditTitle: 'Crédit restant',
        creditText:
            'Somme des factures non réglées. Les factures arrivent avec la phase des prestations.',
        serviceOrdersEmpty: 'Les prestations de ce client apparaîtront ici.',
        invoicesEmpty: 'Les factures de ce client apparaîtront ici.',
        edit: 'Modifier',
        editClient: 'Modifier le client',
        deactivate: 'Désactiver',
        reactivate: 'Réactiver',
        deactivateClientTitle: 'Désactiver ce client ?',
        deactivateClientText:
            'Il n’apparaîtra plus dans les recherches des nouvelles prestations. Son historique est conservé et vous pourrez le réactiver.',
        call: 'Appeler',
        since: (date: string) => `Client depuis le ${date}`,
        quick: {
            title: 'Nouveau client',
            description: 'Le téléphone suffit à retrouver le client plus tard.',
            withVehicle: 'Ajouter un véhicule maintenant',
            vehicleSection: 'Véhicule',
            created: 'Client ajouté.',
            createdWithVehicle: 'Client et véhicule ajoutés.',
        },
    },

    vehicles: {
        title: 'Véhicule',
        addVehicle: 'Ajouter un véhicule',
        editVehicle: 'Modifier le véhicule',
        fields: {
            plate: 'Matricule',
            plateHint: 'Tel qu’écrit sur la plaque, ex. 01234-118-16.',
            vin: 'Numéro de châssis',
            vinHint:
                'Recommandé : il identifie le véhicule même si la plaque change.',
            brand: 'Marque',
            model: 'Modèle',
            modelPlaceholder: 'Choisir la marque d’abord',
            year: 'Année',
            energy: 'Énergie',
            mileage: 'Kilométrage actuel',
            mileageHint:
                'Facultatif : ensuite mis à jour par chaque prestation.',
            notes: 'Remarque',
        },
        vehicle: 'Véhicule',
        owner: 'Propriétaire',
        currentOwner: 'Propriétaire actuel',
        lastMileage: 'Dernier kilométrage',
        unknown: 'Non renseigné',
        details: 'Informations',
        changeOwner: 'Changer de propriétaire',
        changeOwnerText:
            'Le véhicule passe au nouveau propriétaire avec tout son historique : les prestations ne bougent pas.',
        newOwner: 'Nouveau propriétaire',
        newOwnerPlaceholder: 'Rechercher par téléphone, nom ou matricule',
        transferDate: 'Date du changement',
        confirmTransfer: 'Changer de propriétaire',
        ownersHistory: 'Historique des propriétaires',
        ownerPeriod: (from: string, to: string | null) =>
            to ? `Du ${from} au ${to}` : `Depuis le ${from}`,
        current: 'Actuel',
        nextOilChange: 'Prochaine vidange',
        nextOilChangeText:
            'Calculée à partir des vidanges enregistrées dans les prestations.',
        logbook: 'Carnet d’entretien',
        logbookText:
            'Les prestations et les kilométrages relevés apparaîtront ici, de la plus récente à la plus ancienne.',
        documents: 'Documents',
        documentsText:
            'Bilan d’entretien à imprimer ou en PDF (avec ou sans les prix).',
        deactivateTitle: 'Désactiver ce véhicule ?',
        deactivateText:
            'Il n’apparaîtra plus dans les recherches des nouvelles prestations. Son carnet est conservé et vous pourrez le réactiver.',
    },

    user: {
        menu: 'Mon compte',
        profile: 'Profil',
        password: 'Mot de passe',
        appearance: 'Apparence',
        logout: 'Se déconnecter',
        roles: { gerant: 'Gérant', employe: 'Employé' },
    },

    auth: {
        loginTitle: 'Connexion',
        loginDescription:
            "Entrez votre nom d'utilisateur et votre mot de passe.",
        username: "Nom d'utilisateur",
        password: 'Mot de passe',
        remember: 'Rester connecté',
        submit: 'Se connecter',
        showPassword: 'Afficher le mot de passe',
        hidePassword: 'Masquer le mot de passe',
        forgotHint:
            'Mot de passe oublié ? Demandez au gérant de le réinitialiser.',
    },

    settings: {
        title: 'Mon compte',
        description:
            'Votre nom, votre mot de passe et l’apparence de l’application.',
        profileTitle: 'Profil',
        profileDescription: 'Le nom affiché dans l’application.',
        name: 'Nom',
        username: "Nom d'utilisateur",
        usernameHint: 'Seul le gérant peut le modifier.',
        role: 'Rôle',
        passwordTitle: 'Mot de passe',
        passwordDescription:
            'Choisissez un mot de passe que vous retiendrez facilement.',
        currentPassword: 'Mot de passe actuel',
        newPassword: 'Nouveau mot de passe',
        confirmPassword: 'Confirmer le nouveau mot de passe',
        savePassword: 'Changer le mot de passe',
        appearanceTitle: 'Apparence',
        appearanceDescription: "Choisissez l'affichage clair ou sombre.",
        themes: { light: 'Clair', dark: 'Sombre', system: 'Comme l’appareil' },
    },

    dashboard: {
        title: 'Accueil',
        greeting: (name: string) => `Bonjour ${name}`,
        description:
            'Le tableau de bord arrive avec les prestations et la caisse.',
    },

    table: {
        previous: 'Précédent',
        next: 'Suivant',
        results: (count: number) =>
            count === 1 ? '1 résultat' : `${count} résultats`,
        page: (current: number, last: number) => `Page ${current} sur ${last}`,
        sortBy: (label: string) => `Trier par ${label}`,
    },

    select: {
        placeholder: 'Choisir…',
        search: 'Tapez pour chercher…',
        addNew: (query: string) => (query ? `Ajouter « ${query} »` : 'Ajouter'),
    },

    confirm: {
        reason: 'Motif',
        reasonPlaceholder: 'Expliquez en quelques mots…',
    },

    period: {
        today: "Aujourd'hui",
        week: 'Semaine',
        month: 'Mois',
        year: 'Année',
        custom: 'Du… au…',
        from: 'Du',
        to: 'Au',
    },

    statuses: {
        serviceOrder: { en_cours: 'En cours', terminee: 'Terminée' },
        invoice: {
            impayee: 'Impayée',
            partielle: 'Partielle',
            payee: 'Payée',
            annulee: 'Annulée',
        },
        appointment: { prevu: 'Prévu', effectue: 'Effectué', annule: 'Annulé' },
        stock: { ok: 'En stock', bas: 'Stock bas', rupture: 'Rupture' },
    },
} as const;
