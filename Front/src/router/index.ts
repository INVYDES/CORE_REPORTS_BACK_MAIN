import { createRouter, createWebHistory } from 'vue-router'
import AuthLayout from '../layouts/AuthLayout.vue'
import AppLayout from '../layouts/AppLayout.vue'
import { useAuthStore } from '../stores/authStore'
import { appBase } from '../utils/basePath'

const router = createRouter({
    history: createWebHistory(appBase),
    // Al navegar: respeta atrás/adelante y hace scroll suave a los anclas (#precios, etc.).
    scrollBehavior(to, _from, savedPosition) {
        if (savedPosition) return savedPosition
        if (to.hash) return { el: to.hash, top: 80, behavior: 'smooth' }
        return { top: 0 }
    },
    routes: [
        // Landing pública (home). Visible para todos, con o sin sesión.
        {
            path: '/',
            name: 'landing',
            component: () => import('../views/LandingView.vue')
        },

        {
            path: '/',
            component: AuthLayout,
            children: [
                {
                    path: 'login',
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
                    meta: { requiresAuth: true }
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

        // Páginas legales públicas
        {
            path: '/aviso-de-privacidad',
            name: 'privacy',
            component: () => import('../views/LegalView.vue'),
            meta: { legal: 'privacy' }
        },
        {
            path: '/terminos',
            name: 'terms',
            component: () => import('../views/LegalView.vue'),
            meta: { legal: 'terms' }
        },
        {
            path: '/aviso-legal',
            name: 'legal-notice',
            component: () => import('../views/LegalView.vue'),
            meta: { legal: 'legal-notice' }
        },

        // Cualquier ruta no definida → 404 personalizada
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('../views/NotFoundView.vue')
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
                    component: () => import('../views/admin/HistoryView.vue'),
                    meta: { allowedRoles: [1, 3] }
                },

                {
                    path: 'company-profile',
                    name: 'company-profile',
                    component: () => import('../views/admin/CompanyProfileView.vue'),
                    meta: { allowedRoles: [2] }
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
                    meta: { allowedRoles: [1, 2, 3] }
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
                    component: () => import('../views/admin/AnalysisView.vue'),
                    meta: { allowedRoles: [1, 2] }
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
