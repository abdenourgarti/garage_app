export type ReminderStatus = 'ok' | 'soon' | 'overdue';

/** Une échéance (ReminderService::forVehicles). */
export type DueItem = {
    type_id: number;
    type: string;
    done_at: string;
    done_km: number;
    due_km: number;
    remaining_km: number | null;
    status: ReminderStatus;
};

/** Un véhicule à relancer (ReminderService::toCall). */
export type ReminderRow = {
    id: number;
    plate: string;
    vehicle: string;
    client_id: number;
    client: string;
    phone: string | null;
    estimated_km: number | null;
    due: DueItem[];
    remaining_km: number | null;
    status: ReminderStatus;
    called_at: string | null;
    called: boolean;
};
