import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'

// Layouts
import MainLayout from '@/layouts/MainLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AdminLayout from '@/layouts/AdminLayout.vue'

// Pages
import HomePage from '@/pages/public/HomePage.vue'
import CampaignListPage from '@/pages/public/CampaignListPage.vue'
import CampaignDetailPage from '@/pages/public/CampaignDetailPage.vue'

import LoginPage from '@/pages/auth/LoginPage.vue'
import RegisterPage from '@/pages/auth/RegisterPage.vue'
import ForgotPasswordPage from '@/pages/auth/ForgotPasswordPage.vue'
import ResetPasswordPage from '@/pages/auth/ResetPasswordPage.vue'

import CreatorDashboardPage from '@/pages/creator/CreatorDashboardPage.vue'
import CreateCampaignPage from '@/pages/creator/CreateCampaignPage.vue'
import EditCampaignPage from '@/pages/creator/EditCampaignPage.vue'

import BackerDashboardPage from '@/pages/backer/BackerDashboardPage.vue'
import WalletPage from '@/pages/backer/WalletPage.vue'
import TransactionsPage from '@/pages/backer/TransactionsPage.vue'
import NotificationsPage from '@/pages/backer/NotificationsPage.vue'

import VerifyEmailPage from '@/pages/auth/VerifyEmailPage.vue'

import AdminDashboardPage from '@/pages/admin/AdminDashboardPage.vue'
import AdminCampaignsPage from '@/pages/admin/AdminCampaignsPage.vue'
import AdminUsersPage from '@/pages/admin/AdminUsersPage.vue'

import NotFoundPage from '@/pages/NotFoundPage.vue'

const routes = [
  // Public & User Main Layout Routes
  {
    path: '/',
    component: MainLayout,
    children: [
      {
        path: '',
        name: 'home',
        component: HomePage,
      },
      {
        path: 'campaigns',
        name: 'campaigns.index',
        component: CampaignListPage,
      },
      {
        path: 'campaigns/:slug',
        name: 'campaigns.show',
        component: CampaignDetailPage,
      },
      // Backer / User Routes
      {
        path: 'dashboard',
        name: 'backer.dashboard',
        component: BackerDashboardPage,
        meta: { requiresAuth: true },
      },
      {
        path: 'wallet',
        name: 'wallet',
        component: WalletPage,
        meta: { requiresAuth: true },
      },
      {
        path: 'transactions',
        name: 'transactions',
        component: TransactionsPage,
        meta: { requiresAuth: true },
      },
      {
        path: 'notifications',
        name: 'notifications',
        component: NotificationsPage,
        meta: { requiresAuth: true },
      },
      // Creator Routes inside Main Layout
      {
        path: 'creator/dashboard',
        name: 'creator.dashboard',
        component: CreatorDashboardPage,
        meta: { requiresAuth: true, roles: ['creator', 'admin'] },
      },
      {
        path: 'creator/campaigns/create',
        name: 'creator.campaigns.create',
        component: CreateCampaignPage,
        meta: { requiresAuth: true, roles: ['creator', 'admin'], requiresVerified: true },
      },
      {
        path: 'creator/campaigns/:slug/edit',
        name: 'creator.campaigns.edit',
        component: EditCampaignPage,
        meta: { requiresAuth: true, roles: ['creator', 'admin'], requiresVerified: true },
      },
    ],
  },

  // Auth Layout Routes
  {
    path: '/',
    component: AuthLayout,
    children: [
      {
        path: 'login',
        name: 'login',
        component: LoginPage,
        meta: { guestOnly: true },
      },
      {
        path: 'register',
        name: 'register',
        component: RegisterPage,
        meta: { guestOnly: true },
      },
      {
        path: 'forgot-password',
        name: 'forgot-password',
        component: ForgotPasswordPage,
        meta: { guestOnly: true },
      },
      {
        path: 'reset-password',
        name: 'reset-password',
        component: ResetPasswordPage,
        meta: { guestOnly: true },
      },
      {
        path: 'verify-email',
        name: 'verify-email',
        component: VerifyEmailPage,
      },
    ],
  },

  // Admin Control Panel Layout Routes
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, roles: ['admin'] },
    children: [
      {
        path: '',
        redirect: '/admin/dashboard',
      },
      {
        path: 'dashboard',
        name: 'admin.dashboard',
        component: AdminDashboardPage,
      },
      {
        path: 'campaigns',
        name: 'admin.campaigns',
        component: AdminCampaignsPage,
      },
      {
        path: 'campaigns/:slug',
        name: 'admin.campaigns.show',
        component: CampaignDetailPage,
      },
      {
        path: 'users',
        name: 'admin.users',
        component: AdminUsersPage,
      },
      {
        path: 'notifications',
        name: 'admin.notifications',
        component: NotificationsPage,
      },
    ],
  },

  // 404 Catch All
  {
    path: '/:pathMatch(.*)*',
    component: MainLayout,
    children: [
      {
        path: '',
        name: 'not-found',
        component: NotFoundPage,
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }
    return { top: 0 }
  },
})

// Navigation Guard
router.beforeEach((to) => {
  const authStore = useAuthStore()

  // 1. Guest Only routes (Login, Register)
  if (to.meta.guestOnly && authStore.isAuthenticated) {
    if (authStore.isAdmin) {
      return '/admin/dashboard'
    }
    if (authStore.isCreator) {
      return '/creator/dashboard'
    }
    return '/dashboard'
  }

  // 2. Requires Auth
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return {
      path: '/login',
      query: { redirect: to.fullPath },
    }
  }

  // 3. Role-based checks
  if (to.meta.roles && to.meta.roles.length > 0) {
    const userRole = authStore.role
    if (!to.meta.roles.includes(userRole)) {
      // Unauthorized role redirection
      if (authStore.isAdmin) return '/admin/dashboard'
      if (authStore.isCreator) return '/creator/dashboard'
      return '/dashboard'
    }
  }

  // 4. Admin always uses Admin Dashboard layout for campaigns and cannot browse public web
  if (authStore.isAdmin) {
    if (to.name === 'campaigns.show' && to.params.slug) {
      return `/admin/campaigns/${to.params.slug}`
    }
    if (!to.path.startsWith('/admin')) {
      return '/admin/dashboard'
    }
  }
})

export default router
