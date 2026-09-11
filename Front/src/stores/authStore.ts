import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import client from '../api/client';

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

export const useAuthStore = defineStore('auth', () => {
    const token = ref<string | null>(localStorage.getItem('token'));
    const currentUser = ref<UsuarioReal | null>(JSON.parse(localStorage.getItem('user') || 'null'));
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

    if (token.value) client.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;

    const setSession = (user: UsuarioReal, tkn: string) => {
        currentUser.value = user;
        token.value = tkn;
        localStorage.setItem('token', tkn);
        localStorage.setItem('user', JSON.stringify(user));
        client.defaults.headers.common['Authorization'] = `Bearer ${tkn}`;
    };

    const login = async (email: string, password: string) => {
        const { data } = await client.post('/login', { email, password });
        setSession(data.user, data.token);
        return data.user as UsuarioReal;
    };

    const fetchMe = async () => {
        if (!token.value) return null;
        try {
            const { data } = await client.get('/me');
            currentUser.value = data;
            localStorage.setItem('user', JSON.stringify(data));
            return data;
        } catch {
            logout();
            return null;
        }
    };

    const logout = async () => {
        try { if (token.value) await client.post('/logout'); } catch {}
        token.value = null;
        currentUser.value = null;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        delete client.defaults.headers.common['Authorization'];
    };

    const changeUser = (_idUsuario: number) => {
        console.warn('changeUser legacy no-op, use login con otro email');
    };

    return {
        token, currentUser, currentUserCompat, isAuthenticated,
        login, logout, fetchMe, changeUser,
        get currentUserValue() { return currentUserCompat.value; }
    };
});