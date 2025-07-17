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

const fetchCategoryArticles = async (slug) => {
  isLoading.value = true;
  try {
    const response = await axios.get(`/api/categories/${slug}/articles`);
    category.value = response.data.category;
    articles.value = response.data.articles.data;
    pagination.value = response.data.articles;
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
  }
};

watch(() => route.params.slug, (newSlug) => {
  if (newSlug) {
    fetchCategoryArticles(newSlug);
  }
}, { immediate: true });
</script>
<template>
  <main class="p-6 md:p-10">
    <div class="max-w-7xl mx-auto">
      <div v-if="isLoading" class="text-center py-20">載入中...</div>
      <div v-else-if="category">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">
          分類：{{ category.name }}
        </h1>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <ArticleCard v-for="article in articles" :key="article.id" :article="article" />
        </div>
      </div>
      <div v-else class="text-center py-20">找不到該分類。</div>
    </div>
  </main>
</template>