export type RolUsuario = 'admin' | 'propietario';

export type User = {
    id: number;
    name: string;
    email: string;
    rol: RolUsuario;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /**
     * Sirve para no mostrar botones que igual van a dar 403. La restricción de
     * verdad está en el middleware `admin`, no acá.
     */
    esAdmin: boolean;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
