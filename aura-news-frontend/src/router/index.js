import { createRouter, createWebHistory } from 'vue-router';

import HomeView from '../views/HomeView.vue';
import AdminLayout from '../layouts/AdminLayout.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    } else {
      return { top: 0 };
    }
  },
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView
    },
    {
      path: '/articles/:id',
      name: 'article-detail',
      component: () => import('../views/ArticleDetailView.vue')
    },
    {
      path: '/category/:slug',
      name: 'category',
      component: () => import('../views/CategoryView.vue')
    },
    {
      path: '/search',
      name: 'search',
      component: () => import('../views/SearchView.vue')
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue')
    },
    {
      path: '/ai-scan-fake-news',
      name: 'ai-scan-fake-news',
      component: () => import('../views/AIScanFakeNewsView.vue')
    },
    {
      path: '/user/settings',
      name: 'user-settings',
      component: () => import('@/views/UserSettingsView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/auth/google/callback',
      name: 'google-callback',
      component: () => import('../views/GoogleCallbackView.vue')
    },
    {
      path: '/terms',
      name: 'terms',
      component: () => import('@/views/TermsView.vue')
    },
    {
      path: '/privacy',
      name: 'privacy',
      component: () => import('@/views/PrivacyView.vue')
    },

    {
      path: '/admin',
      component: AdminLayout,
      meta: { 
        requiresAuth: true,
        requiresAdmin: true
      },
      children: [
        {
          path: '',
          redirect: '/admin/dashboard',
        },
        {
          path: 'dashboard',
          name: 'admin-dashboard',
          component: () => import('../views/admin/DashboardView.vue')
        },
        {
          path: 'articles',
          name: 'admin-articles',
          component: () => import('../views/admin/ArticleListView.vue')
        },
        {
          path: 'articles/create',
          name: 'admin-articles-create',
          component: () => import('../views/admin/ArticleFormView.vue')
        },
        {
          path: 'articles/:id/edit',
          name: 'admin-articles-edit',
          component: () => import('../views/admin/ArticleFormView.vue'),
          props: true 
        },
        {
          path: 'categories',
          name: 'admin-categories',
          component: () => import('../views/admin/CategoryListView.vue')
        },
      ]
    }
  ]
});

export default router;