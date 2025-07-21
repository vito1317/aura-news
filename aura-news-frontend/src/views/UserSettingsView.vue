<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';

const authStore = useAuthStore();
const nickname = ref(authStore.user?.nickname || '');
const password = ref('');
const password2 = ref('');
const message = ref('');
const error = ref('');

const API_BASE = import.meta.env.VITE_API_BASE || 'https://api-news.vito1317.com';

const updateProfile = async () => {
  error.value = '';
  message.value = '';
  try {
    await axios.put(`${API_BASE}/api/user/profile`, { nickname: nickname.value });
    authStore.user.nickname = nickname.value;
    localStorage.setItem('user', JSON.stringify(authStore.user));
    message.value = '暱稱已更新';
  } catch (e) {
    error.value = '暱稱更新失敗';
  }
};

const updatePassword = async () => {
  error.value = '';
  message.value = '';
  if (!password.value || password.value !== password2.value) {
    error.value = '請輸入相同的新密碼';
    return;
  }
  try {
    await axios.put(`${API_BASE}/api/user/password`, { password: password.value });
    message.value = '密碼已更新';
    password.value = '';
    password2.value = '';
  } catch (e) {
    error.value = '密碼更新失敗';
  }
};
</script>
<template>
  <div class="max-w-lg mx-auto py-10 px-4">
    <h1 class="text-2xl font-bold mb-6">用戶設定</h1>
    <div class="bg-white rounded-xl shadow p-6 mb-8">
      <h2 class="text-lg font-semibold mb-4">修改暱稱</h2>
      <form @submit.prevent="updateProfile" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">暱稱</label>
          <input v-model="nickname" class="w-full border rounded-md p-2 mt-1" maxlength="40" />
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md font-bold">儲存暱稱</button>
      </form>
    </div>
    <div class="bg-white rounded-xl shadow p-6 mb-8">
      <h2 class="text-lg font-semibold mb-4">修改密碼</h2>
      <form @submit.prevent="updatePassword" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">新密碼</label>
          <input type="password" v-model="password" class="w-full border rounded-md p-2 mt-1" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">再次輸入新密碼</label>
          <input type="password" v-model="password2" class="w-full border rounded-md p-2 mt-1" />
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md font-bold">儲存密碼</button>
      </form>
    </div>
    <div v-if="message" class="text-green-600 font-bold mb-2">{{ message }}</div>
    <div v-if="error" class="text-red-600 font-bold mb-2">{{ error }}</div>
  </div>
</template> 