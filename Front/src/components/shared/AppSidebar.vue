<script setup lang="ts">
    import { computed } from 'vue'
    import { useRoute } from 'vue-router'
    import { useAuthStore } from '../../stores/authStore'
    import { storeToRefs } from 'pinia'
    
    const authStore = useAuthStore()
    const { currentUser } = storeToRefs(authStore)
    import {
        LayoutDashboard,
        Ticket,
        FileText,
        CalendarClock,
        CalendarPlus,
        BarChart3,
        Building2,
        Network,
        LogOut,
        TicketPlus,
        FilePlus2,
        CalendarDays
    } from 'lucide-vue-next'

    const buildTime = __BUILD_TIME__

    const rolLabels: Record<number, string> = {
        0: 'Cliente',
        1: 'Admin Técnico',
        2: 'Admin Comercial',
        3: 'Técnico'
    }
    const rolClassMap: Record<number, string> = {
        0: 'role-cliente',
        1: 'role-admin-tec',
        2: 'role-admin-com',
        3: 'role-tecnico'
    }
    const roleLabel = computed(() => rolLabels[currentUser.value?.rol ?? -1] || 'Sin rol')
    const roleClass = computed(() => rolClassMap[currentUser.value?.rol ?? -1] || '')

    const route = useRoute()
    
    const props = defineProps<{
        isOpen: boolean
    }>()
    
    const emit = defineEmits(['close', 'openReportModal', 'openTicketModal'])
    
    const menuItems = computed(() => {
        const items = [

            {
                label: 'Vista general',
                path: 'dashboard',
                icon: LayoutDashboard,
                roles: [1, 2, 3] // Admin Tec, Admin Com, Tech
            },

            {
                label: 'Historial',
                path: 'history',
                icon: CalendarDays,
                roles: [1, 3] // Solo Tech Admin y Tech
            },

            {
                label: 'Reportes',
                path: 'reports', // We will update this when you split the views!
                icon: FileText,
                roles: [1, 3] 
            },
            {
                label: 'Nuevo Reporte',
                path: 'reports-new', // We will update this when you split the views!
                icon: FilePlus2,
                roles: [1, 3] 
            },


            {
                label: 'Tickets',
                path: 'tickets',
                icon: Ticket,
                roles: [1, 3]
            },
            
            {
                label: 'Nuevo Ticket',
                path: 'create-ticket',
                icon: TicketPlus,
                roles: [0, 1, 2, 3],
                target: '_blank'
            },

            {
                label: 'Servicios Programados',
                path: 'schedule-list',
                icon: CalendarClock,
                roles: [1, 3]
            },

            {
                label: 'Programar Nuevo Servicio',
                path: 'schedule-manager',
                icon: CalendarPlus,
                roles: [1] 
            },


            {
                label: 'Análisis',
                path: 'analysis',
                icon: BarChart3,
                roles: [1, 2] // Both Admins
            },
            {
                label: 'Gestión de Subdependencias/Áreas',
                path: 'subareas-manager',
                icon: Network,
                roles: [1, 2] // Both Admins
            },
            {
                label: 'Mi Perfil',
                path: 'company-profile',
                icon: Building2,
                roles: [2] // Commercial Admin
            },
        ]
        
        if (!currentUser.value) return []
        return items.filter(item => item.roles.includes(currentUser.value!.rol))
    })
</script>

<!------------------------------------------------------------------------------------------------------------------>

<template>
    <div class="page-container-app-sidebar">
    <aside class="sidebar" :class="{ 'sidebar-open': isOpen }">

        <!-- Cabecera de la barra lateral-->
        <div class = "sidebar-header">
            <div class = "logo-text">coreReports</div>
        </div>

        <nav class = "sidebar-nav">

            <template v-for="item in menuItems" :key="item.path">
                <button v-if="item.path === 'reports-new'" class="nav-item" @click="emit('openReportModal'); emit('close')">
                    <component :is="item.icon" :size="20" />
                    <span>{{ item.label }}</span>
                </button>
                <button v-else-if="item.path === 'create-ticket'" class="nav-item" @click="emit('openTicketModal'); emit('close')">
                    <component :is="item.icon" :size="20" />
                    <span>{{ item.label }}</span>
                </button>
                <router-link v-else
                    :to = "{ name: item.path }"
                    :target="item.target"
                    class = "nav-item"
                    :class = "{ 'active' : route.name === item.path }"
                >
                    <component 
                        :is = "item.icon"
                        :size = "20"
                    />
                    <span>{{ item.label }}</span>            
                </router-link>
            </template>
        </nav>

        <!-- Sidebar Footer-->
        <div class = "sidebar-footer">

            <!-- Rol actual (reemplaza Cambiar Usuario) -->
            <div class="role-container">
                <div class="role-header">
                    <span class="role-badge" :class="roleClass">{{ roleLabel }}</span>
                </div>
                <div class="user-info" v-if="currentUser">
                    <div class="user-name">{{ currentUser.nombre }} {{ currentUser.apellidos }}</div>
                    <div class="user-email">{{ currentUser.email }}</div>
                </div>
            </div>

            <button class = "logout-btn">
                <LogOut :size = "18" />
                <span>Cerrar sesión</span>
            </button>
            <div class="build-time">
                Built: {{ buildTime }}
            </div>
        </div>
    </aside>
    </div>
</template>


<!------------------------------------------------------------------------------------------------------------------>

<style scoped lang="scss">
    $dark-bg: #0f172a;
    $darker-bg: #020617;
    $primary-blue: #3b82f6;
    $text-color: #cbd5e1;

    .sidebar {
        width: 260px;
        height: 100vh;
        background-color: $dark-bg;
        color: $text-color;
        display: flex;
        flex-direction: column;
        border-right: 1px solid rgba(255,255,255,0.05);
        transition: transform 0.3s ease, width 0.3s ease;
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 40;
            transform: translateX(-100%);
            
            &.sidebar-open {
                transform: translateX(0);
            }
        }
    }

    .sidebar-header {
        height: 70px;
        display: flex;
        align-items: center;
        padding: 0 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);

    .logo-text {
        font-size: 1.1rem;
        font-weight: 700;
        color: white;
        letter-spacing: -0.5px;
    }
    }

    .sidebar-nav {
        flex: 1;
        padding: 1.5rem 1rem;
        overflow-y: auto;
  
  .nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    margin-bottom: 4px;
    color: $text-color;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s;
    font-size: 0.95rem;
    font-weight: 500;

    &:hover {
      background-color: rgba(255,255,255,0.05);
      color: white;
    }

    &.active {
      background: linear-gradient(90deg, rgba($primary-blue, 0.15), transparent);
      color: $primary-blue;
      border-left: 3px solid $primary-blue;
    }
  }
  button.nav-item {
    background: transparent;
    border: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
    font-family: inherit;
  }
    }

    .sidebar-footer {
        padding: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.05);
  
    .logout-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 10px;
        background: transparent;
        border: 1px solid rgba(255,255,255,0.1);
        color: $text-color;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    
        &:hover {    
        background-color: rgba(239, 68, 68, 0.1); // Red tint
        color: #ef4444; // Red text
        border-color: rgba(239, 68, 68, 0.2);
        }
    }

    .build-time {
        margin-top: 1rem;
        font-size: 0.7rem;
        color: rgba($text-color, 0.4);
        text-align: center;
        font-family: monospace;
    }

    .role-container {
        margin-bottom: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 8px;
        padding: 0.75rem;
    }

    .role-header {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .role-badge {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,0.1);
        &.role-cliente { background: rgba(148,163,184,0.15); color: #e2e8f0; }
        &.role-admin-tec { background: rgba(59,130,246,0.15); color: #93c5fd; border-color: rgba(59,130,246,0.3); }
        &.role-admin-com { background: rgba(16,185,129,0.15); color: #6ee7b7; border-color: rgba(16,185,129,0.3); }
        &.role-tecnico { background: rgba(245,158,11,0.15); color: #fcd34d; border-color: rgba(245,158,11,0.3); }
    }

    .user-info {
        text-align: center;
        .user-name { font-size: 0.85rem; font-weight: 600; color: white; }
        .user-email { font-size: 0.7rem; color: rgba($text-color, 0.6); word-break: break-all; }
    }
}

</style>