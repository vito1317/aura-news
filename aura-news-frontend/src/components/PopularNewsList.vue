<script setup>
import { RouterLink } from 'vue-router';
import { computed } from 'vue';

const props = defineProps({ articles: Array });

// 直接使用已排序的文章，只取前10篇
const popularArticles = computed(() => {
  if (!props.articles || !Array.isArray(props.articles)) {
    return [];
  }
  
  return props.articles.slice(0, 10);
});
</script>

<template>
  <ol class="space-y-4">
    <li v-for="(article, index) in popularArticles" :key="article.id" class="flex items-start gap-4">
      <span class="text-2xl font-bold text-gray-300 w-8 text-center">{{ index + 1 }}</span>
      <div class="flex-1">
        <RouterLink :to="{ name: 'article-detail', params: { id: article.id } }" class="text-gray-800 hover:text-brand-DEFAULT transition-colors">
          {{ article.title }}
        </RouterLink>
        <div v-if="article.popularity_score" class="flex items-center space-x-1 mt-1">
          <svg class="w-3 h-3 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
          <span class="text-xs text-orange-600 font-medium">{{ Math.round(article.popularity_score) }}</span>
        </div>
      </div>
    </li>
  </ol>
</template>