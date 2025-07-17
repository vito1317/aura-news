<script setup>
import { RouterLink } from 'vue-router';

defineProps({
  article: {
    type: Object,
    required: true
  }
});

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString();
};

const randomGradient = (id) => {
  const colors = [
    ['from-blue-200', 'to-cyan-200'],
    ['from-purple-200', 'to-indigo-200'],
    ['from-green-200', 'to-teal-200'],
    ['from-yellow-200', 'to-orange-200'],
    ['from-pink-200', 'to-rose-200'],
  ];
  const index = id % colors.length;
  return `bg-gradient-to-br ${colors[index][0]} ${colors[index][1]}`;
};
</script>

<template>
  <div class="group flex flex-col rounded-lg overflow-hidden border border-gray-200 hover:border-gray-300 transition-all duration-300">
    <RouterLink :to="{ name: 'article-detail', params: { id: article.id } }" class="block aspect-w-16 aspect-h-9 w-full overflow-hidden">
      <img
        v-if="article.image_url"
        :src="article.image_url"
        :alt="article.title"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
      >
      <div
        v-else
        class="w-full h-full flex items-center justify-center"
        :class="randomGradient(article.id)"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </div>
    </RouterLink>

    <div class="p-4 space-y-2 flex-grow flex flex-col">
      <div class="flex items-center space-x-2 text-xs text-gray-500">
        <span v-if="article.category" class="font-medium text-brand-DEFAULT">{{ article.category.name }}</span>
        <span v-if="article.category">·</span>
        <span>{{ formatDate(article.published_at) }}</span>
      </div>
      <RouterLink :to="{ name: 'article-detail', params: { id: article.id } }" class="block">
         <h2 class="text-lg font-semibold text-gray-900 group-hover:text-brand-DEFAULT transition-colors duration-300 line-clamp-2">{{ article.title }}</h2>
      </RouterLink>
    </div>
  </div>
</template>