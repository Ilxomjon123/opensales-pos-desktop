export type User = {
    id: number;
    name: string;
    username: string;
    avatar?: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type UserRole = 'super_admin' | 'dealer' | 'warehouse' | 'deliveryman';

export type Auth = {
    user: User;
    role?: UserRole;
    dealer_id?: number | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
