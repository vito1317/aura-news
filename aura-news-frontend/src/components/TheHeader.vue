<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const searchQuery = ref('');
const navLinks = ref([]);
// --- ↓↓↓ 新增 RWD 相關狀態 ↓↓↓ ---
const isMenuOpen = ref(false);
// 監聽路由變化，在跳轉頁面後自動關閉選單
router.afterEach(() => {
  isMenuOpen.value = false;
});
// --- ↑↑↑ RWD 狀態結束 ↑↑↑ ---
const handleSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ name: 'search', query: { q: searchQuery.value } });
    searchQuery.value = '';
  }
};
onMounted(async () => {
  try {
    const response = await axios.get('/api/categories');
    const homeLink = { name: '首頁', path: '/', slug: null };
    const aiScanLink = { name: 'AI 假新聞查證', path: '/ai-scan-fake-news', slug: 'ai-scan-fake-news' };
    const categoryLinks = response.data.map(cat => ({
      name: cat.name,
      path: `/category/${cat.slug}`,
      slug: cat.slug,
    }));
    navLinks.value = [homeLink, aiScanLink, ...categoryLinks];
  } catch (error) {
    console.error('無法載入導覽列分類:', error);
    navLinks.value = [
      { name: '首頁', path: '/', slug: null },
      { name: 'AI 假新聞查證', path: '/ai-scan-fake-news', slug: 'ai-scan-fake-news' }
    ];
  }
});
</script>

<template>
  <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <!-- 左側 Logo -->
        <div class="flex items-center">
          <RouterLink to="/" class="text-2xl font-bold text-gray-900">Aura News</RouterLink>
        </div>
        <!-- 中間導覽連結 (桌機版) -->
        <div class="hidden md:flex md:space-x-1">
          <RouterLink
            v-for="link in navLinks"
            :key="link.name"
            :to="link.slug ? link.path : link.path"
            class="text-gray-600 hover:text-brand-DEFAULT px-4 py-2 rounded-md text-sm font-medium transition-colors"
            active-class="text-brand-DEFAULT font-semibold"
          >
            {{ link.name }}
          </RouterLink>
        </div>
        <!-- 右側操作區 -->
        <div class="flex items-center">
          <!-- 搜尋框 (桌機/平板) -->
          <form @submit.prevent="handleSearch" class="relative hidden sm:block">
            <input type="text" v-model="searchQuery" placeholder="搜尋..." class="border-gray-300 rounded-full pl-4 pr-10 py-1 text-sm focus:ring-brand-DEFAULT focus:border-brand-DEFAULT">
            <button type="submit" class="absolute inset-y-0 right-0 px-3 text-gray-500">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </button>
          </form>
          <!-- 使用者圖示 -->
          <RouterLink to="/login" class="ml-4 text-gray-500 hover:text-brand-DEFAULT transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
          </RouterLink>
          <!-- 手機版漢堡選單按鈕 (只在 md 以下的螢幕顯示) -->
          <div class="ml-2 md:hidden">
            <button @click="isMenuOpen = !isMenuOpen" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-DEFAULT">
              <span class="sr-only">Open main menu</span>
              <!-- 關閉時的圖示 -->
              <svg v-if="!isMenuOpen" class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
              <!-- 開啟時的圖示 -->
              <svg v-else class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
        </div>
      </div>
    </nav>
    <!-- 手機版彈出選單 -->
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div v-if="isMenuOpen" class="md:hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
          <!-- 手機版搜尋框 -->
          <form @submit.prevent="handleSearch" class="mb-3">
            <div class="relative">
              <input type="text" v-model="searchQuery" placeholder="搜尋..." class="w-full border-gray-300 rounded-full pl-4 pr-10 py-2 text-base focus:ring-brand-DEFAULT focus:border-brand-DEFAULT">
              <button type="submit" class="absolute inset-y-0 right-0 px-3 text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
              </button>
            </div>
          </form>
          <RouterLink
            v-for="link in navLinks"
            :key="link.name"
            :to="link.slug ? link.path : link.path"
            class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-white hover:bg-brand-DEFAULT"
            active-class="bg-brand-light text-brand-dark"
          >
            {{ link.name }}
          </RouterLink>
        </div>
      </div>
    </transition>
  </header>
</template>