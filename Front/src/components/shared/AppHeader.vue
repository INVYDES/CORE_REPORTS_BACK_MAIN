<script setup lang = "ts">

    import { computed, ref, onMounted } from 'vue'
    import { Bell, Menu, Mail } from 'lucide-vue-next'
    import { useAuthStore } from '../../stores/authStore'
    import { storeToRefs } from 'pinia'
    import { getDependenciaName } from '../../data/mockDependencias'
    import { notificacionesApi } from '../../api/notificaciones'

    const emit = defineEmits(['toggle-sidebar'])

    const authStore = useAuthStore()
    const { currentUser } = storeToRefs(authStore)

    const enterpriseName = computed(() => {
        const depId = (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia
        return getDependenciaName(depId)
    })

    const notifications = ref<any[]>([])
    const showDropdown = ref(false)
    const loadingNotif = ref(false)
    const unreadCount = computed(() => notifications.value.filter(n => !n.leida_at).length)

    const fetchNotifications = async () => {
        try {
            loadingNotif.value = true
            const res = await notificacionesApi.list({ per_page: 20 })
            const list = res.data || res
            notifications.value = Array.isArray(list) ? list : []
        } catch {} finally { loadingNotif.value = false }
    }
    const toggleDropdown = () => {
        showDropdown.value = !showDropdown.value
        if (showDropdown.value) fetchNotifications()
    }
    const markRead = async (id:number) => {
        try { await notificacionesApi.markRead(id); const n = notifications.value.find(x=>x.id===id); if(n) n.leida_at = new Date().toISOString() } catch {}
    }
    const markAllRead = async () => {
        try { await notificacionesApi.markAllRead(); notifications.value.forEach(n=> n.leida_at = n.leida_at || new Date().toISOString()) } catch {}
    }
    onMounted(fetchNotifications)

    const initialName = computed(() => {
        const nombre = currentUser.value?.nombre;
        return nombre ? nombre.charAt(0).toUpperCase() : '';
    });
    const initialLastName = computed(() => {
        const apellidos = currentUser.value?.apellidos;
        return apellidos ? apellidos.charAt(0).toUpperCase() : '';
    });

    const roleLabel = computed(() => {
        if(currentUser.value?.rol === 0) return 'Default'
        else if(currentUser.value?.rol === 1) return 'Administrador Técnico'
        else if(currentUser.value?.rol === 2) return 'Administrador Comercial'
        else if(currentUser.value?.rol === 3) return 'Técnico'
        else return 'Desconocido'
    });

</script>

<!------------------------------------------------------------------------------------------------------------------>

<template>
    <div class = "page-container-app-header">
    <header class = "app-header">
        <div class = "header-left">
            <!-- Hamburger menu for mobile -->
            <button class="hamburger-btn" @click="$emit('toggle-sidebar')">
                <Menu :size="24" />
            </button>
            <div class = "title-header">
                Plataforma Integral de Servicios e Incidencias
            </div>
            <div class = "enterprise-header">
                {{ enterpriseName }}
            </div>
        </div>
        <!--Search bar-->

        <!-- Right Header -->
        <div class = "header-right">

            <!--Icon real conectado a /api/notificaciones -->
            <div class = "icon-btn" @click="toggleDropdown" title="Notificaciones">
                <Bell :size = "20" />
                <span v-if="unreadCount>0" class = "badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>

                <div v-if="showDropdown" class="notif-dropdown" @click.stop>
                    <div class="notif-header">
                        <span class="notif-title">Notificaciones</span>
                        <button v-if="unreadCount>0" @click="markAllRead" class="mark-all">Marcar todas leídas</button>
                    </div>
                    <div v-if="loadingNotif" class="notif-empty">Cargando...</div>
                    <div v-else-if="notifications.length===0" class="notif-empty">Sin notificaciones</div>
                    <div v-else class="notif-list">
                        <div v-for="n in notifications" :key="n.id" class="notif-item" :class="{ unread: !n.leida_at }" @click="markRead(n.id)">
                            <div class="notif-icon"><Mail :size="14" /></div>
                            <div class="notif-content">
                                <div class="notif-asunto">{{ n.titulo || n.asunto }}</div>
                                <div class="notif-cuerpo">{{ n.cuerpo?.slice(0,80) }}</div>
                                <div class="notif-meta">{{ n.referencia_tipo }} #{{ n.referencia_id }} · {{ new Date(n.created_at).toLocaleDateString() }}</div>
                            </div>
                            <span v-if="!n.leida_at" class="dot"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!--User Profile-->

            <div class = "user-profile">
                <div class = "avatar">{{ initialName }}{{ initialLastName }}</div>
                <div class = "user-info">
                    <span class = "name">{{ currentUser?.nombre }} {{ currentUser?.apellidos }}</span>
                    <span class = "role">{{ roleLabel }}</span>
                </div>
            </div>
        </div>
           
    </header>
    </div>
</template>

<!------------------------------------------------------------------------------------------------------------------>

<style scoped lang="scss">
    .app-header {
        height: 70px;
        background-color: white;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 2rem;
        position: sticky;
        top: 0;
        z-index: 50;
        gap: 2rem;
    }
 
    .header-left {
        display: flex;
        align-items: center;
        overflow: hidden;
    }

    .hamburger-btn {
        display: none;
        background: none;
        border: none;
        cursor: pointer;
        color: #1e293b;
        padding: 0;
        margin-right: 1rem;
    }

    @media (max-width: 768px) {
        .hamburger-btn {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .enterprise-header,
        .title-header {
            display: none; // Hide on mobile to save space
        }
        
        .user-info {
            display: none; // Hide name/role text, show only avatar
        }

        .user-profile {
            padding-left: 0.5rem;
            border-left: none;
        }

        .header-right {
            gap: 0.75rem;
        }

        .app-header {
            padding: 0 1rem;
            gap: 0.5rem;
        }
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex-shrink: 0;
        position: relative;
        z-index: 20;
    }

    .title-header{
        font-size: 1.1rem;
        font-weight: 600;
        color:#1e293b;
        letter-spacing: 0.04em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-right: 1rem;
    }

    .enterprise-header {
        font-size: 0.9rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex-shrink: 0;
    }

    .icon-btn {
        background: none;
        border: none;
        position: relative;
        cursor: pointer;
        color: #64748b;
        padding: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
  
        &:hover {
            color: #3b82f6;
        }
  
        .badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #ef4444;
            color: white;
            font-size: 0.65rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 2px solid white;
        }
    }

    .notif-dropdown {
        position: absolute;
        top: 40px;
        right: 0;
        width: 360px;
        max-height: 420px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        z-index: 100;
        display: flex;
        flex-direction: column;
    }
    .notif-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        background: #f8fafc;
        .notif-title { font-weight: 700; font-size: 0.9rem; color: #0f172a; }
        .mark-all { background: none; border: none; color: #3b82f6; font-size: 0.75rem; font-weight: 600; cursor: pointer; }
    }
    .notif-empty { padding: 24px; text-align: center; color: #94a3b8; font-size: 0.85rem; }
    .notif-list { overflow-y: auto; max-height: 340px; }
    .notif-item {
        display: flex;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: background 0.15s;
        &:hover { background: #f8fafc; }
        &.unread { background: #eff6ff; }
        .notif-icon { width: 32px; height: 32px; border-radius: 8px; background: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notif-content { flex: 1; min-width: 0; }
        .notif-asunto { font-weight: 600; font-size: 0.85rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notif-cuerpo { font-size: 0.78rem; color: #64748b; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notif-meta { font-size: 0.7rem; color: #94a3b8; margin-top: 4px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; flex-shrink: 0; margin-top: 8px; }
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-left: 1.5rem;
        border-left: 1px solid #e2e8f0;
  
        .avatar {
            width: 36px;
            height: 36px;
            background-color: #1e293b;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
  
        .user-info {
            display: flex;
            flex-direction: column;
    
            .name {
                font-size: 0.85rem;
                font-weight: 600;
                color: #1e293b;
                white-space: nowrap;
            }
    
            .role {
                font-size: 0.75rem;
                color: #64748b;
            }
        }
    }
</style>