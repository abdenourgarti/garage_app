import type { SelectOption } from '@/components/app/search-select';

/** Rendez-vous tel que l'envoie AppointmentController::list(). */
export type Appointment = {
    id: number;
    /** « 2026-10-27 » */
    day: string;
    /** « 09:30 » */
    time: string;
    status: 'prevu' | 'effectue' | 'annule';
    notes: string | null;
    /** label : nom, description : téléphone. */
    client: SelectOption;
    /** label : matricule, description : marque et modèle. */
    vehicle: SelectOption | null;
    services: { id: number; name: string }[];
    order: { id: number; number: string } | null;
};
