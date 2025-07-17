<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import noImage from '@/assets/no-image.jpg';

const route = useRoute();
const article = ref(null);
const isLoading = ref(true);
const error = ref(null);

const safeSummary = computed(() => {
  if (article.value && article.value.summary) {
    const html = marked.parse(article.value.summary);
    return DOMPurify.sanitize(html);
  }
  return '';
});
const safeContent = computed(() => {
  if (article.value && article.value.content) {
    const html = marked.parse(article.value.content);
    return DOMPurify.sanitize(html);
  }
  return '';
});

const share = (platform) => {
  const url = encodeURIComponent(window.location.href);
  const title = encodeURIComponent(article.value?.title || document.title);
  let shareUrl = '';
  if (platform === 'facebook') {
    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
  } else if (platform === 'twitter') {
    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
  } else if (platform === 'line') {
    shareUrl = `https://social-plugins.line.me/lineit/share?url=${url}`;
  }
  if (shareUrl) {
    window.open(shareUrl, '_blank', 'noopener,width=600,height=600');
  }
};

const canWebShare = typeof navigator !== 'undefined' && !!navigator.share;

const webShare = async () => {
  if (!article.value) return;
  try {
    await navigator.share({
      title: article.value.title,
      text: article.value.summary || '',
      url: window.location.href
    });
  } catch (err) {
    console.warn('Web Share 失敗:', err);
  }
};

onMounted(async () => {
  const articleId = route.params.id;
  try {
    const response = await axios.get(`https://api-news.vito1317.com/api/articles/${articleId}`);
    article.value = response.data;
  } catch (err) {
    console.error('文章獲取失敗:', err);
    error.value = '無法載入此篇文章，可能已被刪除或網址錯誤。';
  } finally {
    isLoading.value = false;
  }
});

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).replace(/\//g, '.'); 
}
</script>

<template>
  <div class="py-12 md:py-24">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div v-if="isLoading" class="text-center py-20 text-gray-500">載入中...</div>
      <div v-else-if="error" class="bg-red-100 text-red-700 p-4 rounded">{{ error }}</div>
      <article v-else-if="article">
        <div class="mb-4">
          <span class="inline-block bg-brand-light text-brand-dark font-semibold px-3 py-1 rounded-full text-sm">
            {{ article.category?.name || '新聞' }}
          </span>
        </div>
        <h1 class="text-3xl md:text-5xl font-bold text-gray-900 leading-tight mb-4">
          {{ article.title }}
        </h1>
        <div class="flex items-center space-x-4 text-sm text-gray-500 mb-8">
          <span>作者: {{ article.author || '匿名' }}</span>
          <span>|</span>
          <span>{{ formatDate(article.published_at) }}</span>
        </div>
        <div class="mb-8 rounded-lg overflow-hidden shadow-lg">
          <img
            :src="article.image_url || noImage"
            :alt="article.title"
            @error="e => e.target.src = noImage"
          />
        </div>
        <div v-if="article.summary" class="mb-8 p-6 bg-gray-50 rounded-lg border-l-4 border-brand-DEFAULT">
          <div class="text-lg italic text-gray-700 leading-relaxed" v-html="safeSummary"></div>
          <div class="text-xs text-gray-400 mt-2 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 4V2m0 20v-2m7-7h-2M4 15H2m2.93-7.07l-1.42-1.42m12.02 12.02l1.42 1.42M19.07 4.93l-1.42 1.42M6.34 17.66l-1.42 1.42M8 12l4 4m0 0l4-4m-4 4V8"/></svg>
            （AI 摘要）
          </div>
        </div>
        <div 
          class="prose prose-lg max-w-none prose-h2:font-bold prose-h2:text-gray-800" 
          v-html="safeContent">
        </div>
        <div class="mt-12 py-6 border-t border-gray-200">
          <div class="flex flex-col sm:flex-row sm:items-center flex-wrap gap-x-4 gap-y-2">
            <span class="text-gray-600 font-semibold mb-2 sm:mb-0">分享文章：</span>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
              <button v-if="canWebShare" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors" @click="webShare">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline w-5 h-5 mr-1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                </svg>
                分享
              </button>
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors" @click="() => share('facebook')">Facebook</button>
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors" @click="() => share('twitter')">Twitter</button>
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors" @click="() => share('line')">Line</button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<style>
.prose p {
  margin-bottom: 1.25em;
}
.prose h2 {
  font-size: 1.5em;
  margin-top: 1.5em;
  margin-bottom: 1em;
}
</style> 