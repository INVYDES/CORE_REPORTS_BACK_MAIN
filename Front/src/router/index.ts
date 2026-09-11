import { createRouter, createWebHistory } from 'vue-router'

import AuthLayout from '../layouts/AuthLayout.vue'
import AppLayout from '../layouts/AppLayout.vue'
import { useAuthStore } from '../stores/authStore'

const router = createRouter
    (
        {
            history: createWebHistory(import.meta.env.BASE_URL),
            routes:
                [
                    {
                        path: '/',
                        component: AuthLayout,
                        children:
                            [
                                {
                                    path: '', //root of AuthLayout OK
                                    name: 'login',
                                    component: () => import('../views/auth/LoginView.vue')
                                },

                                {
                                    path: 'register',
                                    name: 'register',
                                    component: () => import('../views/auth/RegisterView.vue')
                                },

                                {
                                    path: 'onboarding',
                                    name: 'onboarding',
                                    component: () => import('../views/auth/OnboardingWizardView.vue')
                                }
                            ]
                    },

                    {
                        path: '/new-ticket',
                        name: 'create-ticket',
                        component: () => import('../views/admin/tickets/TicketCreateView.vue')
                    },

                    {
                        path: '/app',
                        component: AppLayout,
                        children:
                            [
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
                                    component: () => import('../views/admin/services/ServiceCreateView.vue')
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
                                    path: 'company-profile',
                                    name: 'company-profile',
                                    component: () => import('../views/admin/CompanyProfileView.vue')
                                },

                                {
                                    path: 'subareas',
                                    name: 'subareas-manager',
                                    component: () => import('../views/admin/company/SubareaManagerView.vue'),
                                    meta: { allowedRoles: [1, 2] } // Tech Admin & Commer Admin only
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

router.beforeEach((to, _from, next) => {
    const allowedRoles = to.meta.allowedRoles as number[] | undefined

    if (allowedRoles && allowedRoles.length > 0) {
        const authStore = useAuthStore()
        const userRole = authStore.currentUser?.rol

        if (userRole === undefined || !allowedRoles.includes(userRole)) {
            return next({ name: 'dashboard' })
        }
    }

    next()
})

export default router