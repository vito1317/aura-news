<script setup>
import { onMounted, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const error = ref(null);
const loading = ref(true);

onMounted(async () => {
  const code = route.query.code;
  if (!code) {
    error.value = 'Google 認證失敗，缺少 code 參數';
    loading.value = false;
    return;
  }
  try {
    const res = await axios.get('/api/auth/google/callback', { params: { code } });
    axios.defaults.headers.common['Authorization'] = `Bearer ${res.data.token}`;
    localStorage.setItem('token', res.data.token);
    await authStore.fetchUser();
    router.replace({ name: 'home' });
  } catch (err) {
    error.value = 'Google 登入失敗';
    loading.value = false;
  }
});
</script>
<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="p-8 bg-white rounded-lg shadow-md w-96 text-center">
      <div v-if="loading">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p class="text-lg font-bold">Google 登入中，請稍候...</p>
      </div>
      <div v-else-if="error" class="text-red-600 font-bold">
        {{ error }}
      </div>
    </div>
  </div>
</template> 