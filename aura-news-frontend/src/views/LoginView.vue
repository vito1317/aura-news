<!-- LoginView.vue -->
<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();
const form = ref({ email: '', password: '' });
const error = ref(null);

const handleLogin = async () => {
  error.value = null;
  try {
    await authStore.login(form.value);
    if (authStore.isAdmin) {
      router.push({ name: 'admin-dashboard' });
    } else {
      router.push({ name: 'home' });
    }
  } catch (err) {
    error.value = '登入失敗，請檢查帳號或密碼。';
  }
};
</script>
<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="p-8 bg-white rounded-lg shadow-md w-96">
      <h1 class="text-2xl font-bold mb-6 text-center">登入 Aura News</h1>
      <form @submit.prevent="handleLogin" class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">電子郵件</label>
          <input type="email" id="email" v-model="form.email" class="w-full p-2 border rounded-md mt-1">
        </div>
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700">密碼</label>
          <input type="password" id="password" v-model="form.password" class="w-full p-2 border rounded-md mt-1">
        </div>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <button type="submit" class="w-full bg-blue-600 text-white p-3 rounded-md text-lg font-bold shadow-lg hover:bg-blue-800 transition-colors border-2 border-blue-600">登入</button>
      </form>
    </div>
  </div>
</template>