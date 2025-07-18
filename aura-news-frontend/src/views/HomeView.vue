<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import FeaturedArticleHero from '@/components/FeaturedArticleHero.vue';
import ArticleListItem from '@/components/ArticleListItem.vue';
import SidebarWidget from '@/components/SidebarWidget.vue';
import PopularNewsList from '@/components/PopularNewsList.vue';
import { useHead } from '@vueuse/head';

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
const isLoading = ref(true);

function isValidArticle(article) {
  const invalidMsg = '{emptyPanelMsg}';
  return (
    article.title && article.title.length > 5 &&
    article.summary && article.summary.length > 10 &&
    article.image_url && article.image_url.length > 10 &&
    !article.summary.includes(invalidMsg) &&
    !article.image_url.includes(invalidMsg)
  );
}

onMounted(async () => {
  try {
    const response = await axios.get('/api/articles');
    articles.value = (response.data.data || []).filter(isValidArticle);
  } catch (error) {
    console.error("無法載入首頁文章:", error);
  } finally {
    isLoading.value = false;
  }
});

const carouselArticles = computed(() => articles.value.slice(0, 4));
const latestArticles = computed(() => articles.value.slice(4, 10));
const popularArticles = computed(() => articles.value.slice(10, 15));
</script>

<template>
  <main class="bg-white">
    <FeaturedArticleHero v-if="carouselArticles.length" :articles="carouselArticles" />
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-10 lg:gap-8">
        <div class="lg:col-span-7">
          <h2 class="text-2xl font-bold text-gray-900 mb-4">最新新聞</h2>
          <div v-if="isLoading">載入中...</div>
          <div v-else class="space-y-6">
            <ArticleListItem v-for="article in latestArticles" :key="article.id" :article="article" />
          </div>
        </div>
        <aside class="lg:col-span-3 mt-8 lg:mt-0">
          <SidebarWidget title="熱門新聞">
            <PopularNewsList :articles="popularArticles" />
          </SidebarWidget>
        </aside>
      </div>
    </div>
  </main>
</template>
