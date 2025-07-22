<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import FeaturedArticleHero from '@/components/FeaturedArticleHero.vue';
import ArticleListItem from '@/components/ArticleListItem.vue';
import SidebarWidget from '@/components/SidebarWidget.vue';
import PopularNewsList from '@/components/PopularNewsList.vue';
import MarqueeNews from '@/components/MarqueeNews.vue';
import RecommendedArticles from '@/components/RecommendedArticles.vue';
import { useHead } from '@vueuse/head';
import { fetchRecommendedArticles } from '@/api/article.js';

useHead({
  title: 'Aura News - AI 新聞平台',
  meta: [
    { name: 'description', content: 'Aura News - AI 新聞平台，最新、最即時的新聞內容。' },
    { property: 'og:title', content: 'Aura News - AI 新聞平台' },
    { property: 'og:description', content: 'Aura News - AI 新聞平台，最新、最即時的新聞內容。' },
    { property: 'og:type', content: 'website' },
    { property: 'og:image', content: '/aura-news.png' },
    { property: 'og:url', content: typeof window !== 'undefined' ? window.location.href : '' },
    { name: 'twitter:card', content: 'summary_large_image' }
  ]
});

const articles = ref([]);
const totalArticles = ref(0);
const totalViews = ref(0);
const avgCredibility = ref(0);
const isLoading = ref(true);
const currentPage = ref(1);
const hasMore = ref(true);
const isLoadingMore = ref(false);
const perPage = ref(36);
const API_BASE = import.meta.env.VITE_API_BASE || 'https://api-news.vito1317.com';

function isValidArticle(article) {
  const invalidMsg = '{emptyPanelMsg}';
  return (
    article.title && article.title.length > 5 &&
    article.summary && article.summary.length > 10 &&
    article.image_url && article.image_url.length > 10 &&
    !article.summary.includes(invalidMsg) &&
    !article.image_url.includes(invalidMsg) &&
    article.content && article.content.length > 100
  );
}

function updatePerPage() {
  if (window.innerWidth < 640) {
    perPage.value = 12;
  } else {
    perPage.value = 36;
  }
}

onMounted(() => {
  updatePerPage();
  window.addEventListener('resize', updatePerPage);
  // 用 setTimeout 確保 perPage 設定後再載入
  setTimeout(async () => {
  await loadPopularArticles();
  await loadArticles();
    // 先取推薦
    const rec = (await fetchRecommendedArticles()).slice(0, 3).map(a => ({ ...a, carouselType: '推薦' }));
    // 最新排除已在推薦的
    const latest = articles.value.filter(a => !rec.some(r => r.id === a.id)).slice(0, 3).map(a => ({ ...a, carouselType: '最新' }));
    // 熱門排除已在推薦/最新的
    const hot = popularArticles.value.filter(a => !rec.some(r => r.id === a.id) && !latest.some(l => l.id === a.id)).slice(0, 3).map(a => ({ ...a, carouselType: '熱門' }));
    recommendedArticles.value = rec;
    latestArticlesForHero.value = latest;
    popularArticlesForHero.value = hot;
  }, 0);
});

const loadArticles = async (page = 1, append = false) => {
  if (page === 1) {
    isLoading.value = true;
  } else {
    isLoadingMore.value = true;
  }
  
  try {
    const response = await axios.get(`${API_BASE}/api/articles?page=${page}&per_page=${perPage.value}&sort_by=latest`);
    const validArticles = (response.data.data || []).filter(isValidArticle);
    
    totalArticles.value = response.data.total || 0;
    
    if (append) {
      articles.value.push(...validArticles);
    } else {
      articles.value = validArticles;
    }
    
    currentPage.value = page;
    hasMore.value = articles.value.length < totalArticles.value;
    
    if (page === 1) {
      await fetchStats();
    }
    
  } catch (error) {
    console.error("無法載入文章:", error);
  } finally {
    isLoading.value = false;
    isLoadingMore.value = false;
  }
};

const loadMore = async () => {
  if (isLoadingMore.value || !hasMore.value) return;
  await loadArticles(currentPage.value + 1, true);
};

const fetchStats = async () => {
  try {
    const response = await axios.get(`${API_BASE}/api/articles/stats`);
    totalViews.value = response.data.total_views || 0;
    avgCredibility.value = response.data.avg_credibility || 0;
  } catch (error) {
    console.error("無法載入統計資料:", error);
    totalViews.value = articles.value.reduce((sum, article) => sum + (article.view_count || 0), 0);
    avgCredibility.value = articles.value.length > 0 
      ? Math.round(articles.value.reduce((sum, article) => sum + (article.credibility_score || 0), 0) / articles.value.length)
      : 0;
  }
};

const carouselArticles = computed(() => {
  // 取 4 篇，3 篇最新，1 篇熱門，去除重複
  const latestRaw = latestArticles.value.slice(0, 3);
  const latest = latestRaw.map(a => {
    if (a.carouselType === '最新') return a;
    return { ...a, carouselType: '最新' };
  });
  const latestIds = new Set(latest.map(a => a.id));
  const hotRaw = popularArticles.value.filter(a => !latestIds.has(a.id)).slice(0, 1);
  const hot = hotRaw.map(a => {
    if (a.carouselType === '熱門') return a;
    return { ...a, carouselType: '熱門' };
  });
  let result = [...latest, ...hot];
  if (result.length < 4) {
    const moreHotRaw = popularArticles.value.filter(a => !result.some(b => b.id === a.id)).slice(0, 4 - result.length);
    const moreHot = moreHotRaw.map(a => {
      if (a.carouselType === '熱門') return a;
      return { ...a, carouselType: '熱門' };
    });
    result = [...result, ...moreHot];
  }
  return result;
});

// 新增：排除輪播圖已出現的新聞
const listArticles = computed(() => {
  const carouselIds = new Set(carouselArticles.value.map(a => a.id));
  return articles.value.filter(a => !carouselIds.has(a.id));
});

const latestArticles = computed(() => {
  return articles.value;
});

const popularArticles = ref([]); // 預設空陣列
const recommendedArticles = ref([]);
const latestArticlesForHero = ref([]);
const popularArticlesForHero = ref([]);

const loadPopularArticles = async () => {
  try {
    const response = await axios.get(`${API_BASE}/api/articles?page=1&per_page=15&sort_by=popularity`);
    console.log('API response:', response.data);
    const validArticles = (response.data.data || []).filter(isValidArticle);
    popularArticles.value = validArticles.slice(0, 10);
    console.log('popularArticles after load:', popularArticles.value);
  } catch (error) {
    console.error("無法載入熱門文章:", error);
  }
};

const heroArticles = computed(() => {
  const result = [];
  for (let i = 0; i < 3; i++) {
    if (recommendedArticles.value[i]) result.push(recommendedArticles.value[i]);
    if (latestArticlesForHero.value[i]) result.push(latestArticlesForHero.value[i]);
    if (popularArticlesForHero.value[i]) result.push(popularArticlesForHero.value[i]);
  }
  return result;
});

const stats = computed(() => {
  return { 
    totalArticles: totalArticles.value, 
    totalViews: totalViews.value, 
    avgCredibility: avgCredibility.value
  };
});
</script>

<template>
  <main class="bg-white">
    <!-- 跑馬燈放在 header 下方 -->
    <MarqueeNews :articles="popularArticles" />
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white py-2 sm:py-3 md:py-4">
      <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
        <div class="grid grid-cols-3 gap-1 sm:gap-2 md:gap-4 lg:gap-8 text-center">
          <div class="flex flex-col items-center">
            <span class="text-base sm:text-lg md:text-xl lg:text-2xl font-bold">{{ stats.totalArticles }}</span>
            <span class="text-xs sm:text-sm opacity-90">總文章數</span>
          </div>
          <div class="flex flex-col items-center">
            <span class="text-base sm:text-lg md:text-xl lg:text-2xl font-bold">{{ stats.totalViews.toLocaleString() }}</span>
            <span class="text-xs sm:text-sm opacity-90">文章總觀看次數</span>
          </div>
          <div class="flex flex-col items-center">
            <span class="text-base sm:text-lg md:text-xl lg:text-2xl font-bold">{{ stats.avgCredibility }}%</span>
            <span class="text-xs sm:text-sm opacity-90">平均可信度</span>
          </div>
        </div>
      </div>
    </div>

    <FeaturedArticleHero v-if="heroArticles.length > 0" :articles="heroArticles" class="overflow-hidden" />
    
    <div class="max-w-7xl mx-auto py-3 sm:py-4 md:py-6 lg:py-8 px-3 sm:px-4 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-10 lg:gap-6 xl:gap-8">
        <div class="lg:col-span-7">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-3 sm:mb-4 md:mb-6 gap-2">
            <h2 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900">最新新聞</h2>
            <div class="text-xs sm:text-sm text-gray-500">
              第 {{ currentPage }} 頁，顯示 {{ articles.length }} 篇，共 {{ totalArticles }} 篇
            </div>
          </div>
          
          <div v-if="isLoading" class="text-center py-6 sm:py-8 md:py-12">
            <div class="animate-spin w-6 h-6 sm:w-8 sm:h-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-3 sm:mb-4"></div>
            <p class="text-sm sm:text-base text-gray-500">載入中...</p>
          </div>
          
          <div v-else-if="listArticles.length === 0" class="text-center py-6 sm:py-8 md:py-12">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-300 mx-auto mb-3 sm:mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-sm sm:text-base text-gray-500">暫無最新文章</p>
          </div>
          
          <div v-else class="space-y-3 sm:space-y-4 md:space-y-6">
            <ArticleListItem v-for="article in listArticles" :key="article.id" :article="article" />
            
            <div v-if="hasMore" class="text-center pt-4 sm:pt-6 md:pt-8">
              <button 
                @click="loadMore"
                :disabled="isLoadingMore"
                class="inline-flex items-center px-3 sm:px-4 md:px-6 py-2 sm:py-3 border border-transparent text-sm sm:text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg v-if="isLoadingMore" class="animate-spin -ml-1 mr-2 sm:mr-3 h-4 w-4 sm:h-5 sm:w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ isLoadingMore ? '載入中...' : '載入更多文章' }}
              </button>
            </div>
            
            <div v-else class="text-center py-4 sm:py-6 md:py-8">
              <div class="text-gray-500">
                <svg class="w-6 h-6 sm:w-8 sm:h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm sm:text-base">已載入全部 {{ totalArticles }} 篇文章</p>
              </div>
            </div>
          </div>
        </div>
        
        <aside class="lg:col-span-3 mt-4 sm:mt-6 lg:mt-0">
          <SidebarWidget title="熱門新聞">
            <PopularNewsList :articles="popularArticles" />
          </SidebarWidget>
          
          <RecommendedArticles class="mt-3 sm:mt-4 md:mt-6" />
          
          <div class="mt-3 sm:mt-4 md:mt-6 bg-gray-50 rounded-lg p-3 sm:p-4">
            <h3 class="text-sm sm:text-base md:text-lg font-semibold text-gray-800 mb-2 sm:mb-3">平台資訊</h3>
            <div class="space-y-1 sm:space-y-2 text-xs sm:text-sm text-gray-600">
              <div class="flex justify-between">
                <span>總文章數</span>
                <span class="font-medium">{{ totalArticles }}</span>
              </div>
              <div class="flex justify-between">
                <span>已載入文章</span>
                <span class="font-medium">{{ articles.length }}</span>
              </div>
              <div class="flex justify-between">
                <span>熱門文章</span>
                <span class="font-medium">{{ popularArticles.length }}</span>
              </div>
              <div class="flex justify-between">
                <span>平台平均可信度</span>
                <span class="font-medium">{{ stats.avgCredibility }}%</span>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </main>
</template>
