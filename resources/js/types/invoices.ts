export type InvoiceStatus = 'impayee' | 'partielle' | 'payee' | 'annulee';

export type InvoiceRow = {
    id: number;
    number: string;
    issued_at: string;
    status: InvoiceStatus;
    client_name: string;
    plate: string;
    total: string;
    amount_paid: string;
    remaining: string;
};
