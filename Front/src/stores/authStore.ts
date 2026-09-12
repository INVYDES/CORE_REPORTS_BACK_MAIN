import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import client from '../api/client';
import { getToken, getUser, setSession, clearSession } from '../api/session';

export interface UsuarioReal {
    id: number;
    dependencia_id: number;
    numero_empleado: string | null;
    rol: number;
    nombre: string;
    apellidos: string;
    email: string;
    estado: boolean;
    dependencia?: any;
}

/** Roles del dominio (espejo del enum RolUsuario del backend). */
export const ROLES = {
    CLIENTE: 0,
    ADMIN_TEC: 1,
    ADMIN_COM: 2,
    TECNICO: 3,
} as const;

export const useAuthStore = defineStore('auth', () => {
    const token = ref<string | null>(getToken());
    const currentUser = ref<UsuarioReal | null>(getUser());
    const currentUserCompat = computed(() => {
        if (!currentUser.value) return null as any;
        const u = currentUser.value;
        return {
            ...u,
            idUsuario: u.id,
            idDependencia: u.dependencia_id,
            correo: u.email,
        };
    });

    const isAuthenticated = computed(() => !!token.value && !!currentUser.value);

    // Helpers de rol
    const hasRole = (...roles: number[]) => !!currentUser.value && roles.includes(currentUser.value.rol);
    const isAdmin = computed(() => hasRole(ROLES.ADMIN_TEC, ROLES.ADMIN_COM));
    const isTecnico = computed(() => hasRole(ROLES.TECNICO));
    const isCliente = computed(() => hasRole(ROLES.CLIENTE));

    if (token.value) client.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;

    const setTokenHeader = (tkn: string | null) => {
        if (tkn) {
            client.defaults.headers.common['Authorization'] = `Bearer ${tkn}`;
        } else {
            delete client.defaults.headers.common['Authorization'];
        }
    };

    const applySession = (user: UsuarioReal, tkn: string) => {
        currentUser.value = user;
        token.value = tkn;
        setSession(tkn, user);
        setTokenHeader(tkn);
        // Set JWT cookie for edge workers detection (Secure, SameSite=Strict)
        try {
          document.cookie = `jwt=${tkn}; Path=/; Secure; SameSite=Strict`;
        } catch (e) {
          console.warn('Unable to set JWT cookie', e);
        }
    };

    const login = async (email: string, password: string) => {
        const { data } = await client.post('/login', { email, password });
        applySession(data.user, data.token);
        return data.user as UsuarioReal;
    };

    const fetchMe = async () => {
        if (!token.value) return null;
        try {
            const { data } = await client.get('/me');
            currentUser.value = data;
            setSession(token.value, data);
            return data;
        } catch {
            await logout();
            return null;
        }
    };

    const logout = async () => {
        try { if (token.value) await client.post('/logout'); } catch {}
        token.value = null;
        currentUser.value = null;
        clearSession();
        delete client.defaults.headers.common['Authorization'];
        // Remove JWT cookie
        try {
          document.cookie = 'jwt=; Path=/; Max-Age=0; Secure; SameSite=Strict';
        } catch (e) {
          console.warn('Unable to delete JWT cookie', e);
        }
    };

    const changeUser = (_idUsuario: number) => {
        console.warn('changeUser legacy no-op, use login con otro email');
    };

    return {
        token, currentUser, currentUserCompat, isAuthenticated,
        hasRole, isAdmin, isTecnico, isCliente,
        login, logout, fetchMe, changeUser,
        get currentUserValue() { return currentUserCompat.value; }
    };
});