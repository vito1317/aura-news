<script setup>
import { RouterLink, useRouter } from 'vue-router';
import { Swiper, SwiperSlide } from 'swiper/vue';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';
import { ref } from 'vue';

// SwiperCore.use([Autoplay, Navigation, Pagination]); // 已移除

const props = defineProps({
  articles: {
    type: Array,
    required: true
  }
});

const currentSlide = ref(0);
function isActive(article, idx) {
  return idx === currentSlide.value;
}
const router = useRouter();
function searchByKeyword(kw) {
  router.push({ name: 'search', query: { q: kw.trim() } });
}
// 移除 JS 動畫控制
</script>

<template>
  <Swiper
    :modules="[Navigation, Pagination, Autoplay]"
    :slides-per-view="1"
    :loop="true"
    :autoplay="{ delay: 5000, disableOnInteraction: false }"
    navigation
    pagination
    class="bg-gray-800 text-white h-[400px] sm:h-[450px] md:h-[550px]"
    @slideChange="(swiper) => { if (currentSlide && typeof currentSlide === 'object' && 'value' in currentSlide) { currentSlide.value = swiper.realIndex; } }"
  >
    <SwiperSlide v-for="(article, idx) in articles" :key="article.id">
      <div class="relative w-full h-full">
        <img :src="article.image_url" :alt="article.title" class="absolute inset-0 w-full h-full object-cover opacity-50">
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
        <div class="relative max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-full flex flex-col justify-end items-start text-left pb-8 sm:pb-16 md:pb-24 hero-content">
          <div class="w-full lg:w-2/3">
            <!-- 最新/熱門/推薦標籤與熱門度分數 -->
            <div v-if="article.carouselType || article.popularity_score" class="inline-block mb-2 sm:mb-3 mr-2 align-middle">
              <span v-if="article.carouselType === '推薦'" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500 text-white shadow">推薦</span>
              <span v-else-if="article.carouselType === '最新'" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-500 text-white shadow">最新</span>
              <span v-else-if="article.carouselType === '熱門'" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-500 text-white shadow">熱門</span>
              <span v-if="article.popularity_score" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-600 shadow popularity-hover-scale animate-popularity-glow">
                <svg class="w-3 h-3 mr-1 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                熱門度 {{ Math.round(article.popularity_score) }}%
              </span>
            </div>
            <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-extrabold tracking-tight leading-tight mb-2 sm:mb-4">{{ article.title }}</h1>
            <p class="text-sm sm:text-base md:text-lg text-gray-300 mb-4 sm:mb-6 max-w-3xl line-clamp-2">{{ article.summary }}</p>
            <div v-if="article.keywords" class="mb-2 flex flex-wrap gap-2 rwd-keywords">
              <span class="text-xs text-blue-200 rwd-keywords-label">推薦原因：</span>
              <span
                v-for="kw in article.keywords.split(',')"
                :key="kw"
                class="inline-block bg-blue-50 text-blue-700 px-2 py-0.5 rounded mr-1 mb-1 cursor-pointer hover:bg-blue-200 hover:text-blue-900 transition rwd-keyword-tag"
                @click="searchByKeyword(kw)"
              >
                {{ kw.trim() }}
              </span>
            </div>
            <!-- 文章資訊 - 桌面版：水平排列 -->
            <div class="hidden sm:flex sm:items-center sm:space-x-4 text-sm text-gray-300 mb-4 sm:mb-6">
              <span>作者: {{ article.author || '匿名' }}</span>
              <span>|</span>
              <span>{{ new Date(article.published_at).toLocaleDateString('zh-TW') }}</span>
              <span>|</span>
              <span class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                {{ article.view_count || 0 }} 次觀看
              </span>
            </div>
            <!-- 文章資訊 - 手機版：垂直排列 -->
            <div class="sm:hidden space-y-1 text-xs text-gray-300 mb-4">
              <div class="break-words">作者: {{ article.author || '匿名' }}</div>
              <div>{{ new Date(article.published_at).toLocaleDateString('zh-TW') }}</div>
              <div class="flex items-center">
                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                {{ article.view_count || 0 }} 次觀看
              </div>
            </div>
            <RouterLink :to="{ name: 'article-detail', params: { id: article.id } }" class="inline-block bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 sm:py-3 px-4 sm:px-6 rounded-md transition-colors text-sm sm:text-base">閱讀更多</RouterLink>
          </div>
        </div>
      </div>
    </SwiperSlide>
  </Swiper>
</template>

<style scoped>
.rwd-keywords {
  flex-wrap: wrap;
  row-gap: 0.25rem;
  column-gap: 0.5rem;
  align-items: center;
}
.rwd-keywords-label {
  font-size: 0.75rem;
  color: #60a5fa;
  margin-bottom: 0.25rem;
}
.rwd-keyword-tag {
  font-size: 0.85rem;
  margin-bottom: 0.25rem;
}
@media (max-width: 640px) {
  .rwd-keywords-label {
    font-size: 0.7rem;
  }
  .rwd-keyword-tag {
    font-size: 0.7rem;
    padding: 0.15rem 0.5rem;
  }
}
</style>