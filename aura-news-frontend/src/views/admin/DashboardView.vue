<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const stats = ref(null);
const isLoading = ref(true);
const error = ref(null);

onMounted(async () => {
  try {
    const response = await axios.get('/api/admin/stats');
    stats.value = response.data;
  } catch (err) {
    error.value = '無法載入儀表板數據。';
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">儀表板總覽</h1>
    <div v-if="isLoading">載入數據中...</div>
    <div v-else-if="error" class="text-red-500">{{ error }}</div>
    <!-- Responsive grid: 1 col on mobile, 2 on md, 4 on lg -->
    <div v-else-if="stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
      <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
        <h3 class="text-sm font-medium text-gray-500">總文章數</h3>
        <p class="mt-2 text-3xl font-bold text-gray-900">{{ stats.total_articles }}</p>
      </div>
      <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
        <h3 class="text-sm font-medium text-gray-500">已發布</h3>
        <p class="mt-2 text-3xl font-bold text-gray-900">{{ stats.published_articles }}</p>
      </div>
      <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
        <h3 class="text-sm font-medium text-gray-500">草稿中</h3>
        <p class="mt-2 text-3xl font-bold text-gray-900">{{ stats.draft_articles }}</p>
      </div>
      <div class="bg-white p-4 sm:p-6 rounded-lg shadow">
        <h3 class="text-sm font-medium text-gray-500">待審核</h3>
        <p class="mt-2 text-3xl font-bold text-gray-900">{{ stats.pending_articles }}</p>
      </div>
    </div>
  </div>
</template>