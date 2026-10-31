export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
};

export type TwoFactorState = {
    enabled: boolean;
    pending: boolean;
};

export type Auth = {
    user: User;
    isSuper: boolean;
    /** Every permission the signed-in user holds (all of them for the super user). */
    permissions: string[];
    twoFactor: TwoFactorState;
};
