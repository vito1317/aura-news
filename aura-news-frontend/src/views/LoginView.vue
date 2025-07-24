<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import {
  startRegistration,
  startAuthentication,
} from '@simplewebauthn/browser';

console.log('LoginView loaded');

const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();
const form = ref({ email: '', password: '', nickname: '' });
const error = ref(null);
const isRegister = ref(false);

const handleLogin = async () => {
  error.value = null;
  try {
    await authStore.login({ email: form.value.email, password: form.value.password });
    if (authStore.isAdmin) {
      router.push({ name: 'admin-dashboard' });
    } else {
      router.push({ name: 'home' });
    }
  } catch (err) {
    error.value = '登入失敗，請檢查帳號或密碼。';
  }
};

const handleRegister = async () => {
  error.value = null;
  try {
    await authStore.register({ email: form.value.email, password: form.value.password, nickname: form.value.nickname });
    if (authStore.isAdmin) {
      router.push({ name: 'admin-dashboard' });
    } else {
      router.push({ name: 'home' });
    }
  } catch (err) {
    error.value = '註冊失敗，請檢查資料或換一個信箱。';
  }
};

// Google OAuth 整合
const handleGoogleLogin = async () => {
  error.value = null;
  try {
    const res = await axios.get('/api/auth/google/redirect');
    window.location.href = res.data.url;
  } catch (err) {
    error.value = 'Google 登入初始化失敗';
  }
};

// Passkey 註冊
const handlePasskeyRegister = async () => {
  console.log('Passkey register clicked!');
  error.value = null;
  try {
    // 1. 檢查 email 是否已註冊過 Passkey
    const check = await axios.get(`/api/passkey/check?email=${encodeURIComponent(form.value.email)}`);
    if (check.data.exists) {
      error.value = '此帳號已註冊過 Passkey，請至用戶設定綁定新 Passkey';
      return;
    }
    // 2. 從後端取得註冊 options
    const { data: options } = await axios.post('/api/passkey/register/options', {
      email: form.value.email,
      name: form.value.nickname || form.value.email
    });
    // 3. 呼叫 WebAuthn API
    const attResp = await startRegistration(options);
    // 4. 傳送 attestation 給後端
    const { data: result } = await axios.post('/api/passkey/register/verify', {
      credential: attResp,
      email: form.value.email
    });
    alert('Passkey 註冊成功，請用 Passkey 登入！');
    isRegister.value = false;
    error.value = 'Passkey 註冊成功，請用 Passkey 登入！';
  } catch (err) {
    console.error('Passkey registration error:', err);
    if (err.response && err.response.status === 409) {
      const msg = err.response.data?.message;
      if (msg === '此帳號已註冊，請登入後至用戶設定綁定 Passkey') {
        error.value = msg;
      } else {
        error.value = '此帳號已註冊過 Passkey，請至用戶設定綁定新 Passkey';
      }
    } else if (err.name === 'InvalidStateError') {
      error.value = '此帳號已註冊過 Passkey，請至用戶設定綁定新 Passkey';
    } else {
      error.value = 'Passkey 註冊失敗，請稍後再試。';
    }
  }
};

// Passkey 登入
const handlePasskeyLogin = async () => {
  error.value = null;
  try {
    // 不傳 email
    const { data: options } = await axios.post('/api/passkey/login/options', {});
    const assertionResp = await startAuthentication(options);
    const { data: result } = await axios.post('/api/passkey/login/verify', {
      credential: assertionResp
    });
    axios.defaults.headers.common['Authorization'] = `Bearer ${result.token}`;
    localStorage.setItem('token', result.token);
    await authStore.fetchUser();
    router.replace({ name: 'home' });
  } catch (err) {
    error.value = 'Passkey 登入失敗';
  }
};

// callback 處理
onMounted(async () => {
  const code = route.query.code;
  if (code) {
    error.value = null;
    try {
      // 直接呼叫 callback 取得 token
      const res = await axios.get('/api/auth/google/callback', { params: { code } });
      axios.defaults.headers.common['Authorization'] = `Bearer ${res.data.token}`;
      localStorage.setItem('token', res.data.token);
      await authStore.fetchUser();
      router.replace({ name: 'home' });
    } catch (err) {
      error.value = 'Google 登入失敗';
    }
  }
});
</script>
<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="p-8 bg-white rounded-lg shadow-md w-96">
      <h1 class="text-2xl font-bold mb-6 text-center">{{ isRegister ? '註冊 Aura News' : '登入 Aura News' }}</h1>
      <form @submit.prevent="isRegister ? handleRegister() : handleLogin()" class="space-y-4">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">電子郵件</label>
          <input type="email" id="email" v-model="form.email" class="w-full p-2 border rounded-md mt-1">
        </div>
        <div v-if="isRegister">
          <label for="nickname" class="block text-sm font-medium text-gray-700">暱稱</label>
          <input type="text" id="nickname" v-model="form.nickname" class="w-full p-2 border rounded-md mt-1">
        </div>
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700">密碼</label>
          <input type="password" id="password" v-model="form.password" class="w-full p-2 border rounded-md mt-1">
        </div>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <button type="submit" class="w-full bg-blue-600 text-white p-3 rounded-md text-lg font-bold shadow-lg hover:bg-blue-800 transition-colors border-2 border-blue-600">
          {{ isRegister ? '註冊' : '登入' }}
        </button>
      </form>
      <div class="mt-4 text-center">
        <button @click="isRegister = !isRegister" class="text-blue-600 hover:underline text-sm">
          {{ isRegister ? '已有帳號？點此登入' : '沒有帳號？點此註冊' }}
        </button>
      </div>
      <div class="mt-6 text-center">
        <button @click="handleGoogleLogin" class="w-full flex items-center justify-center gap-2 bg-red-500 hover:bg-red-600 text-white font-bold py-2 rounded-md shadow transition-colors">
          <svg width="20" height="20" viewBox="0 0 48 48"><g><path fill="#4285F4" d="M43.611 20.083H42V20H24v8h11.303C33.972 32.833 29.372 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c2.69 0 5.164.957 7.104 2.527l6.084-6.084C33.963 5.61 29.284 4 24 4 12.954 4 4 12.954 4 24s8.954 20 20 20c11.045 0 20-8.954 20-20 0-1.341-.138-2.651-.389-3.917z"/><path fill="#34A853" d="M6.306 14.691l6.571 4.819C14.54 16.207 18.961 13 24 13c2.69 0 5.164.957 7.104 2.527l6.084-6.084C33.963 5.61 29.284 4 24 4c-7.732 0-14.37 4.41-17.694 10.691z"/><path fill="#FBBC05" d="M24 44c5.304 0 10.13-1.824 13.885-4.958l-6.415-5.264C29.372 36 24 36 24 36c-5.372 0-9.972-3.167-11.303-8.083l-6.57 5.073C9.63 39.59 16.268 44 24 44z"/><path fill="#EA4335" d="M43.611 20.083H42V20H24v8h11.303C34.62 32.254 30.978 35 24 35c-3.961 0-7.437-1.354-9.803-3.573l-6.57 5.073C9.63 39.59 16.268 44 24 44c7.732 0 14.37-4.41 17.694-10.691z"/></g></svg>
          使用 Google 登入
        </button>
      </div>
      <div class="mt-4 text-center">
        <button
          v-if="isRegister"
          @click="handlePasskeyRegister"
          :disabled="!form.email"
          class="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-md shadow transition-colors mb-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7v3.07A7 7 0 0 0 5 21h14a7 7 0 0 0 0-14.93V9a7 7 0 0 0-7-7zm0 2a5 5 0 0 1 5 5v3.07a5 5 0 1 1-10 0V9a5 5 0 0 1 5-5zm0 8a3 3 0 0 0 3-3V9a3 3 0 0 0-6 0v1a3 3 0 0 0 3 3z"/></svg>
          使用 Passkey 註冊
        </button>
        <button
          v-if="!isRegister"
          @click="handlePasskeyLogin"
          class="w-full flex items-center justify-center gap-2 bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 rounded-md shadow transition-colors"
        >
          <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7v3.07A7 7 0 0 0 5 21h14a7 7 0 0 0 0-14.93V9a7 7 0 0 0-7-7zm0 2a5 5 0 0 1 5 5v3.07a5 5 0 1 1-10 0V9a5 5 0 0 1 5-5zm0 8a3 3 0 0 0 3-3V9a3 3 0 0 0-6 0v1a3 3 0 0 0 3 3z"/></svg>
          使用 Passkey 登入
        </button>
      </div>
    </div>
  </div>
</template>