<script setup>
import { RouterLink } from 'vue-router';
defineProps({ article: { type: Object, required: true } });
const formatDate = (dateString) => new Date(dateString).toLocaleDateString();
</script>

<template>
  <RouterLink :to="{ name: 'article-detail', params: { id: article.id } }" class="group grid grid-cols-3 gap-4 items-start">
    
    <div class="col-span-1 h-full">
      <div class="w-full aspect-[9/16] md:aspect-[16/9] overflow-hidden">
        <img 
          v-if="article.image_url" 
          :src="article.image_url" 
          :alt="article.title" 
          class="w-full h-full object-cover rounded-lg"
        >
        <div v-else class="w-full h-full bg-gray-200 rounded-lg flex items-center justify-center">
          <svg class="h-10 w-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
        </div>
      </div>
    </div>

    <div class="col-span-2">
      <div class="flex items-center space-x-2 text-xs text-gray-500 mb-1">
        <span v-if="article.category" class="font-medium text-brand-DEFAULT">{{ article.category.name }}</span>
        <span v-if="article.category">·</span>
        <span>{{ formatDate(article.published_at) }}</span>
      </div>
      <h3 class="text-xl font-bold text-gray-900 mb-1 group-hover:text-brand-DEFAULT transition-colors">
        {{ article.title }}
      </h3>
      <p class="text-gray-600 line-clamp-3">
        {{ article.summary }}
      </p>
    </div>
  </RouterLink>
</template>