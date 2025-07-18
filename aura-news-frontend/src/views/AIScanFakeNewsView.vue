<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const input = ref('');
const isLoading = ref(false);
const result = ref(null);
const error = ref(null);
const progress = ref('');
let pollingInterval = null;

const steps = [
  '已接收請求',
  '正在查看新聞',
  '正在上網搜尋資料',
  'AI 分析中',
  '完成',
];
const currentStep = computed(() => {
  const idx = steps.findIndex(s => progress.value && progress.value.includes(s));
  if (progress.value === '完成') return steps.length - 1;
  return idx >= 0 ? idx : 0;
});

const scanFakeNews = async () => {
  if (!input.value.trim()) {
    error.value = '請輸入新聞內容或網址';
    return;
  }
  isLoading.value = true;
  result.value = null;
  error.value = null;
  progress.value = '';
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
  try {
    const res = await axios.post('/api/ai/scan-fake-news/start', { content: input.value });
    const taskId = res.data.taskId;
    pollProgress(taskId);
  } catch (err) {
    error.value = err.response?.data?.message || 'AI 掃描失敗，請稍後再試';
    isLoading.value = false;
  }
};

const pollProgress = (taskId) => {
  pollingInterval = setInterval(async () => {
    try {
      const res = await axios.get(`/api/ai/scan-fake-news/progress/${taskId}`);
      progress.value = res.data.progress;
      if (res.data.progress === '完成') {
        result.value = { result: res.data.result };
        isLoading.value = false;
        clearInterval(pollingInterval);
      }
    } catch (err) {
      if (err.response && err.response.status === 404) {
        return;
      }
      error.value = '連線中斷，請重試';
      isLoading.value = false;
      clearInterval(pollingInterval);
    }
  }, 1500);
};
</script>

<template>
  <div class="max-w-2xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="mb-8 text-center">
      <h1 class="text-3xl font-extrabold text-blue-800 mb-2">AI 假新聞即時掃描</h1>
      <p class="text-gray-600">輸入新聞內容或網址，AI 將自動查證並給出可信度與建議。</p>
    </div>
    <div class="bg-white rounded-xl shadow p-6 mb-6">
      <label class="block text-gray-700 font-semibold mb-2" for="news-input">新聞內容或網址</label>
      <textarea id="news-input" v-model="input" rows="6" class="w-full border-2 border-blue-200 focus:border-blue-500 rounded-lg p-3 transition" placeholder="請貼上新聞內容或網址..." :disabled="isLoading"></textarea>
      <button @click="scanFakeNews" :disabled="isLoading" class="mt-4 w-full flex justify-center items-center bg-gradient-to-r from-blue-600 to-blue-400 hover:from-blue-700 hover:to-blue-500 text-white font-bold py-2.5 rounded-lg disabled:opacity-50 transition">
        <svg v-if="isLoading" class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
        {{ isLoading ? 'AI 掃描中...' : '開始掃描' }}
      </button>
      <transition name="fade">
        <div v-if="error" class="text-red-600 mt-4 text-center font-bold">{{ error }}</div>
      </transition>
    </div>

    <!-- 進度條/步驟條 -->
    <div v-if="isLoading || (progress && progress !== '完成')" class="mb-8">
      <div class="flex items-center justify-between mb-2">
        <span class="text-blue-700 font-semibold">AI 掃描進度</span>
        <span class="text-sm text-gray-500">{{ progress }}</span>
      </div>
      <div class="flex items-center justify-between">
        <template v-for="(step, idx) in steps" :key="step">
          <div class="flex flex-col items-center flex-1">
            <div :class="[
              'w-8 h-8 rounded-full flex items-center justify-center font-bold mb-1',
              idx < currentStep ? 'bg-blue-500 text-white' : idx === currentStep ? 'bg-blue-200 text-blue-800 border-2 border-blue-500' : 'bg-gray-200 text-gray-400'
            ]">
              {{ idx + 1 }}
            </div>
            <span :class="['text-xs', idx <= currentStep ? 'text-blue-700 font-semibold' : 'text-gray-400']">{{ step }}</span>
          </div>
          <div v-if="idx < steps.length - 1" class="flex-1 h-1 bg-gradient-to-r from-blue-200 to-blue-400 mx-1"></div>
        </template>
      </div>
    </div>

    <!-- 結果區塊 -->
    <transition name="fade">
      <div v-if="result" class="mt-8 p-8 bg-gradient-to-br from-blue-50 to-white rounded-2xl border border-blue-100 shadow">
        <div class="flex items-center mb-4">
          <svg class="h-8 w-8 text-green-500 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2l4-4m5 2a9 9 0 11-18 0a9 9 0 0118 0z"/></svg>
          <h2 class="text-xl font-bold text-blue-800">AI 判斷結果</h2>
        </div>
        <div class="text-gray-800 whitespace-pre-line leading-relaxed text-base">{{ result.result }}</div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style> 