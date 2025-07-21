<script setup>
import { ref, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import ArticleCard from '@/components/ArticleCard.vue';
import { useHead } from '@vueuse/head';

const route = useRoute();
const category = ref(null);
const articles = ref([]);
const pagination = ref({});
const isLoading = ref(true);
const currentPage = ref(1);
const hasMore = ref(true);
const isLoadingMore = ref(false);
const perPage = 12;
const API_BASE = import.meta.env.VITE_API_BASE || 'https://api-news.vito1317.com';

const fetchCategoryArticles = async (slug, page = 1, append = false) => {
  if (page === 1) {
  isLoading.value = true;
  } else {
    isLoadingMore.value = true;
  }
  
  try {
    const response = await axios.get(`${API_BASE}/api/categories/${slug}/articles?page=${page}&per_page=${perPage}`);
    category.value = response.data.category;
    
    if (append) {
      // 追加模式：新增文章到現有列表
      articles.value.push(...response.data.articles.data);
    } else {
      // 初始載入：替換整個列表
    articles.value = response.data.articles.data;
    }
    
    pagination.value = response.data.articles;
    currentPage.value = page;
    hasMore.value = response.data.articles.current_page < response.data.articles.last_page;
    
    if (category.value) {
      useHead({
        title: `分類：${category.value.name} - Aura News`,
        meta: [
          { name: 'description', content: `Aura News - ${category.value.name} 分類新聞列表。` },
          { property: 'og:title', content: `分類：${category.value.name} - Aura News` },
          { property: 'og:description', content: `Aura News - ${category.value.name} 分類新聞列表。` },
          { property: 'og:type', content: 'website' },
          { property: 'og:url', content: typeof window !== 'undefined' ? window.location.href : '' },
          { name: 'twitter:card', content: 'summary_large_image' }
        ]
      });
    }
  } catch (error) {
    console.error("無法載入分類文章:", error);
  } finally {
    isLoading.value = false;
    isLoadingMore.value = false;
  }
};

// 載入更多文章
const loadMore = async () => {
  if (isLoadingMore.value || !hasMore.value) return;
  await fetchCategoryArticles(route.params.slug, currentPage.value + 1, true);
};

watch(() => route.params.slug, (newSlug) => {
  if (newSlug) {
    fetchCategoryArticles(newSlug, 1, false);
  }
}, { immediate: true });
</script>
<template>
  <main class="p-6 md:p-10">
    <div class="max-w-7xl mx-auto">
      <div v-if="isLoading" class="text-center py-20">載入中...</div>
      <div v-else-if="category">
        <div class="flex items-center justify-between mb-8">
          <h1 class="text-3xl font-bold text-gray-900">
          分類：{{ category.name }}
        </h1>
          <div class="text-sm text-gray-500">
            第 {{ currentPage }} 頁，顯示 {{ articles.length }} 篇，共 {{ pagination.total || 0 }} 篇
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <ArticleCard v-for="article in articles" :key="article.id" :article="article" />
        </div>
        
        <!-- 載入更多按鈕 -->
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
            {{ isLoadingMore ? '載入中...' : '載入更多文章' }}
          </button>
        </div>
        
        <!-- 已載入全部文章提示 -->
        <div v-else-if="articles.length > 0" class="text-center py-8">
          <div class="text-gray-500">
            <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p>已載入全部 {{ pagination.total || 0 }} 篇文章</p>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-20">找不到該分類。</div>
    </div>
  </main>
</template>