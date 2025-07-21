<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { fetchRecommendedArticles } from '../api/article.js';

const articles = ref([]);
const router = useRouter();
onMounted(async () => {
  articles.value = await fetchRecommendedArticles();
});

function searchByKeyword(kw) {
  router.push({ name: 'search', query: { q: kw.trim() } });
}
function formatDate(dateString) {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).replace(/\//g, '.');
}
</script>

<template>
  <div>
    <h2 class="text-lg font-bold mb-2">為你推薦</h2>
    <div v-if="articles.length === 0" class="text-gray-500">暫無推薦</div>
    <ul v-else>
      <li v-for="article in articles" :key="article.id" class="mb-6 flex flex-col">
        <div class="w-full aspect-[16/9] overflow-hidden rounded-lg mb-2">
          <img
            v-if="article.image_url"
            :src="article.image_url"
            :alt="article.title"
            class="w-full h-full object-cover object-center"
            style="aspect-ratio:9/16;"
          />
          <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
            <svg class="h-10 w-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <div class="text-xs text-gray-500 mb-1">{{ formatDate(article.published_at) }}</div>
          <h3 class="text-base font-bold text-gray-900 leading-tight mb-1 line-clamp-2">{{ article.title }}</h3>
          <p class="text-gray-600 text-sm line-clamp-2 mb-1">{{ article.summary }}</p>
          <div v-if="article.keywords" class="text-xs text-gray-500 mt-1">
            推薦原因：關鍵字：
            <span
              v-for="kw in article.keywords.split(',')"
              :key="kw"
              class="inline-block bg-blue-50 text-blue-700 px-2 py-0.5 rounded mr-1 cursor-pointer hover:bg-blue-200 hover:text-blue-900 transition"
              @click="searchByKeyword(kw)"
            >
              {{ kw.trim() }}
            </span>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template> 