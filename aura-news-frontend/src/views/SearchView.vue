<script setup>
import { ref, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import ArticleListItem from '@/components/ArticleListItem.vue';
import { useHead } from '@vueuse/head';

const route = useRoute();
const query = ref(route.query.q || '');
const articles = ref([]);
const isLoading = ref(true);

const API_BASE = import.meta.env.VITE_API_BASE || 'https://api-news.vito1317.com';

const searchArticles = async (searchQuery) => {
  if (!searchQuery) return;
  isLoading.value = true;
  try {
    const response = await axios.get(`${API_BASE}/api/search`, { params: { q: searchQuery } });
    articles.value = (response.data.articles.data || []).filter(article => {
      const q = searchQuery.toLowerCase();
      return (
        (article.title && article.title.toLowerCase().includes(q)) ||
        (article.summary && article.summary.toLowerCase().includes(q)) ||
        (article.keywords && article.keywords.toLowerCase().includes(q))
      );
    });
  } catch (error) {
    console.error("搜尋失敗:", error);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  if (query.value) {
    useHead({
      title: `搜尋：${query.value} - Aura News`,
      meta: [
        { name: 'description', content: `Aura News - 搜尋「${query.value}」的新聞結果。` },
        { property: 'og:title', content: `搜尋：${query.value} - Aura News` },
        { property: 'og:description', content: `Aura News - 搜尋「${query.value}」的新聞結果。` },
        { property: 'og:type', content: 'website' },
        { property: 'og:url', content: typeof window !== 'undefined' ? window.location.href : '' },
        { name: 'twitter:card', content: 'summary_large_image' }
      ]
    });
    searchArticles(query.value);
  }
});

watch(() => route.query.q, (newQuery) => {
  if (newQuery && newQuery !== query.value) {
    query.value = newQuery;
    useHead({
      title: `搜尋：${newQuery} - Aura News`,
      meta: [
        { name: 'description', content: `Aura News - 搜尋「${newQuery}」的新聞結果。` },
        { property: 'og:title', content: `搜尋：${newQuery} - Aura News` },
        { property: 'og:description', content: `Aura News - 搜尋「${newQuery}」的新聞結果。` },
        { property: 'og:type', content: 'website' },
        { property: 'og:url', content: typeof window !== 'undefined' ? window.location.href : '' },
        { name: 'twitter:card', content: 'summary_large_image' }
      ]
    });
    searchArticles(newQuery);
  }
});
</script>

<template>
  <main class="p-6 md:p-10">
    <div class="max-w-7xl mx-auto">
      <h1 class="text-3xl font-bold text-gray-900 mb-8">
        搜尋結果： "{{ query }}"
      </h1>
      
      <div v-if="isLoading" class="text-center py-12">
        <div class="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-4"></div>
        <p class="text-gray-500">搜尋中...</p>
      </div>
      
      <div v-else-if="articles.length > 0">
        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
          <div class="flex items-center justify-between">
            <div class="text-sm text-blue-700">
              找到 {{ articles.length }} 篇相關文章
              <span v-if="hasMore" class="text-blue-500">（還有更多結果）</span>
            </div>
            <div class="text-xs text-blue-500">
              第 {{ currentPage }} 頁
            </div>
          </div>
        </div>
        
        <div class="space-y-6">
          <ArticleListItem v-for="article in articles" :key="article.id" :article="article" />
        </div>
        
        <div v-if="hasMore" class="text-center pt-8">
          <button 
            @click="loadMore"
            :disabled="isLoadingMore"
            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <svg v-if="isLoadingMore" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isLoadingMore ? '載入中...' : '載入更多結果' }}
          </button>
        </div>
        
        <div v-else class="text-center py-8">
          <div class="text-gray-500">
            <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p>已載入全部搜尋結果</p>
          </div>
        </div>
      </div>
      
      <div v-else class="text-center py-20">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <p class="text-gray-500 text-lg mb-2">找不到與 "{{ query }}" 相關的文章</p>
        <p class="text-gray-400 text-sm">請嘗試使用不同的關鍵字或檢查拼寫</p>
      </div>
    </div>
  </main>
</template> 