import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isAuthenticated: false,
  }),
  getters: {
    isAdmin: (state) => state.user?.role === 'admin',
  },
  actions: {
    async fetchUser() {
      // 在發送請求前，先檢查本地是否有 token 或 session 的標記
      // 這裡我們假設如果能成功獲取 /api/user，就代表已登入
      try {
        const response = await axios.get('/api/user');
        this.user = response.data;
        this.isAuthenticated = true;
      } catch (error) {
        this.user = null;
        this.isAuthenticated = false;
      }
    },
    async login(credentials) {
      try {
        await axios.get('/sanctum/csrf-cookie');
        await axios.post('/api/login', credentials);
        await this.fetchUser();
      } catch (error) {
        console.error("登入失敗:", error);
        // 拋出錯誤，讓呼叫它的組件可以處理
        throw error;
      }
    },
    async logout() {
      try {
        await axios.post('/api/logout');
        this.user = null;
        this.isAuthenticated = false;
      } catch (error) {
        console.error("登出失敗:", error);
      }
    },
  },
});