export type UserRole = 'gerant' | 'employe';

export type User = {
    id: number;
    name: string;
    username: string;
    role: UserRole;
};

export type Auth = {
    user: User;
    can: {
        cash: boolean;
        employees: boolean;
        admin: boolean;
        references: boolean;
    };
};
