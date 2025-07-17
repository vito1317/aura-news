<script setup>
import { RouterLink } from 'vue-router';
defineProps({ articles: Array });
</script>
<template>
  <ol class="space-y-4">
    <li v-for="(article, index) in articles" :key="article.id" class="flex items-start gap-4">
      <span class="text-2xl font-bold text-gray-300 w-8 text-center">{{ index + 1 }}</span>
      <RouterLink :to="{ name: 'article-detail', params: { id: article.id } }" class="text-gray-800 hover:text-brand-DEFAULT transition-colors">
        {{ article.title }}
      </RouterLink>
    </li>
  </ol>
</template>