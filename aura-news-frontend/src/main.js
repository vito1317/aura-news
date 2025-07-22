import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import axios from 'axios';
import { createHead } from '@vueuse/head';

import './assets/main.css';

axios.defaults.baseURL = 'https://api-news.vito1317.com';
axios.defaults.withCredentials = true;

axios.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

const app = createApp(App);
const pinia = createPinia();
const head = createHead();
app.use(head);
app.use(pinia);

import { useAuthStore } from './stores/auth';
const authStore = useAuthStore();

router.beforeEach((to, from, next) => {
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login' });
  } else if (to.meta.requiresAdmin && authStore.user?.role !== 'admin') {
    next({ name: 'home' }); 
  } else {
    next();
  }
});

authStore.fetchUser().then(() => {
  app.use(router);
  app.mount('#app');
});