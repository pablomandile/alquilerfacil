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
     * Para esconder botones que igual darían 403. La restricción de verdad la
     * hacen el middleware `admin` y las policies, no acá.
     */
    esAdmin: boolean;
    esPropietario: boolean;
    /** Gestiona al menos una propiedad → puede ver los botones "Nuevo…". */
    puedeGestionar: boolean;
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
