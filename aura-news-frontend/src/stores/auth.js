import { defineStore } from 'pinia';
import axios from 'axios';

const API_BASE = import.meta.env.VITE_API_BASE || 'https://api-news.vito1317.com';

export async function getUser() {
  const response = await axios.get(`${API_BASE}/api/user`);
  return response;
}

export async function getCsrfCookie() {
  await axios.get(`${API_BASE}/sanctum/csrf-cookie`);
}

export async function login(credentials) {
  await getCsrfCookie();
  const res = await axios.post(`${API_BASE}/api/login`, credentials);
  return res;
}

export async function register(data) {
  await getCsrfCookie();
  const res = await axios.post(`${API_BASE}/api/register`, data);
  return res;
}

export async function logout() {
  await axios.post(`${API_BASE}/api/logout`);
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    isAuthenticated: !!localStorage.getItem('token'),
  }),
  getters: {
    isAdmin: (state) => state.user?.role === 'admin',
  },
  actions: {
    async fetchUser() {
      try {
        const token = localStorage.getItem('token');
        if (token) {
          axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }
        const response = await axios.get('/api/user');
        this.user = response.data;
        this.isAuthenticated = true;
        localStorage.setItem('user', JSON.stringify(this.user));
      } catch (error) {
        this.user = null;
        this.isAuthenticated = false;
        localStorage.removeItem('user');
        localStorage.removeItem('token');
      }
    },
    async login(credentials) {
      try {
        await axios.get('/sanctum/csrf-cookie');
        const res = await axios.post('/api/login', credentials);
        if (res.data.token) {
          localStorage.setItem('token', res.data.token);
          axios.defaults.headers.common['Authorization'] = `Bearer ${res.data.token}`;
        }
        this.user = res.data.user;
        this.isAuthenticated = true;
        localStorage.setItem('user', JSON.stringify(this.user));
      } catch (error) {
        this.user = null;
        this.isAuthenticated = false;
        localStorage.removeItem('user');
        localStorage.removeItem('token');
        throw error;
      }
    },
    async register(data) {
      try {
        await axios.get('/sanctum/csrf-cookie');
        const res = await axios.post('/api/register', data);
        if (res.data.token) {
          localStorage.setItem('token', res.data.token);
          axios.defaults.headers.common['Authorization'] = `Bearer ${res.data.token}`;
        }
        this.user = res.data.user;
        this.isAuthenticated = true;
        localStorage.setItem('user', JSON.stringify(this.user));
      } catch (error) {
        this.user = null;
        this.isAuthenticated = false;
        localStorage.removeItem('user');
        localStorage.removeItem('token');
        throw error;
      }
    },
    async logout() {
      try {
        await axios.post('/api/logout');
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        delete axios.defaults.headers.common['Authorization'];
        this.user = null;
        this.isAuthenticated = false;
      } catch (error) {
        console.error("登出失敗:", error);
      }
    },
  },
});