<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import ArticleListItem from '@/components/ArticleListItem.vue';

const route = useRoute();
const query = ref(route.query.q || '');
const articles = ref([]);
const isLoading = ref(true);

const searchArticles = async (searchQuery) => {
  if (!searchQuery) return;
  isLoading.value = true;
  try {
    const response = await axios.get('/api/search', { params: { q: searchQuery } });
    articles.value = response.data.articles.data;
  } catch (error) {
    console.error("搜尋失敗:", error);
  } finally {
    isLoading.value = false;
  }
};

watch(() => route.query.q, (newQuery) => {
  if (newQuery) {
    query.value = newQuery;
    searchArticles(newQuery);
  }
}, { immediate: true });
</script>

<template>
  <main class="p-6 md:p-10">
    <div class="max-w-7xl mx-auto">
      <h1 class="text-3xl font-bold text-gray-900 mb-8">
        搜尋結果： "{{ query }}"
      </h1>
      <div v-if="isLoading">載入中...</div>
      <div v-else-if="articles.length > 0" class="space-y-8">
        <ArticleListItem v-for="article in articles" :key="article.id" :article="article" />
      </div>
      <div v-else class="text-center py-20 text-gray-500">找不到與 "{{ query }}" 相關的文章。</div>
    </div>
  </main>
</template> 