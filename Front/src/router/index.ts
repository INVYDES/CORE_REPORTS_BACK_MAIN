import { createRouter, createWebHistory } from 'vue-router'
import AuthLayout from '../layouts/AuthLayout.vue'
import AppLayout from '../layouts/AppLayout.vue'
import { useAuthStore } from '../stores/authStore'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/',
            component: AuthLayout,
            children: [
                {
                    path: '',
                    name: 'login',
                    component: () => import('../views/auth/LoginView.vue'),
                    meta: { guestOnly: true }
                },
                {
                    path: 'register',
                    name: 'register',
                    component: () => import('../views/auth/RegisterView.vue'),
                    meta: { guestOnly: true }
                },
                {
                    path: 'onboarding',
                    name: 'onboarding',
                    component: () => import('../views/auth/OnboardingWizardView.vue'),
                    meta: { guestOnly: true }
                }
            ]
        },

        {
            path: '/new-ticket',
            name: 'create-ticket',
            component: () => import('../views/admin/tickets/TicketCreateView.vue'),
            meta: { requiresAuth: true }
        },

        {
            path: '/licencias/exito',
            name: 'licencia-exito',
            component: () => import('../views/licencias/ExitoView.vue')
        },
        {
            path: '/licencias/error',
            name: 'licencia-error',
            component: () => import('../views/licencias/ErrorView.vue')
        },
        {
            path: '/licencias/pendiente',
            name: 'licencia-pendiente',
            component: () => import('../views/licencias/PendienteView.vue')
        },

        {
            path: '/app',
            component: AppLayout,
            meta: { requiresAuth: true },
            children: [
                {
                    path: 'dashboard',
                    name: 'dashboard',
                    component: () => import('../views/admin/DashboardView.vue')
                },

                {
                    path: 'history',
                    name: 'history',
                    component: () => import('../views/admin/HistoryView.vue')
                },

                {
                    path: 'company-profile',
                    name: 'company-profile',
                    component: () => import('../views/admin/CompanyProfileView.vue')
                },

                {
                    path: 'reports/new',
                    name: 'reports-new',
                    component: () => import('../views/admin/reports/ReportCreateView.vue'),
                    meta: { allowedRoles: [1, 3] }
                },

                {
                    path: 'reports/list',
                    name: 'reports',
                    component: () => import('../views/admin/reports/ReportListView.vue'),
                    meta: { allowedRoles: [1, 3] }
                },

                {
                    path: 'reports/:id',
                    name: 'report-detail',
                    component: () => import('../views/admin/reports/ReportDetailView.vue'),
                    meta: { allowedRoles: [1, 3] }
                },

                {
                    path: 'tickets',
                    name: 'tickets',
                    component: () => import('../views/admin/tickets/TicketListView.vue')
                },

                {
                    path: 'tickets/:id',
                    name: 'ticket-detail',
                    component: () => import('../views/admin/tickets/TicketDetailedView.vue')
                },

                {
                    path: 'schedule/manager',
                    name: 'schedule-manager',
                    component: () => import('../views/admin/services/ServiceCreateView.vue'),
                    meta: { allowedRoles: [1, 2] }
                },

                {
                    path: 'schedule/list',
                    name: 'schedule-list',
                    component: () => import('../views/admin/services/ServiceListView.vue')
                },

                {
                    path: 'services/:id',
                    name: 'service-detail',
                    component: () => import('../views/admin/services/ServiceDetailedView.vue')
                },

                {
                    path: 'analysis',
                    name: 'analysis',
                    component: () => import('../views/admin/AnalysisView.vue')
                },

                {
                    path: 'subareas',
                    name: 'subareas-manager',
                    component: () => import('../views/admin/company/SubareaManagerView.vue'),
                    meta: { allowedRoles: [1, 2] }
                },

                {
                    path: 'evaluacion-servicio/:id',
                    name: 'report-survey',
                    component: () => import('../views/admin/reports/ReportSurveyView.vue'),
                    meta: { allowedRoles: [1, 2, 3] }
                }
            ]
        }
    ]
})

router.beforeEach((to) => {
    const auth = useAuthStore()

    // Rutas solo para invitados: un usuario autenticado va al dashboard
    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'dashboard' }
    }

    // Rutas protegidas: sin sesión → login
    const requiereAuth = to.meta.requiresAuth === true || to.meta.allowedRoles !== undefined
    if (requiereAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } }
    }

    // Control de roles
    const allowedRoles = to.meta.allowedRoles as number[] | undefined
    if (allowedRoles && allowedRoles.length > 0) {
        const userRole = auth.currentUser?.rol
        if (userRole === undefined || !allowedRoles.includes(userRole)) {
            return { name: 'dashboard' }
        }
    }

    return true
})

export default router
