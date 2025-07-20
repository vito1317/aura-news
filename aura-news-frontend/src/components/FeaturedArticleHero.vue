<script setup>
import { RouterLink } from 'vue-router';
import 'vue3-carousel/dist/carousel.css';
import { Carousel, Slide, Pagination, Navigation } from 'vue3-carousel';

defineProps({
  articles: {
    type: Array,
    required: true
  }
});
</script>

<template>
  <Carousel :items-to-show="1" :wrap-around="true" :autoplay="5000" class="bg-gray-800 text-white h-[400px] sm:h-[450px] md:h-[550px]">
    <Slide v-for="article in articles" :key="article.id">
      <div class="relative w-full h-full">
        <img :src="article.image_url" :alt="article.title" class="absolute inset-0 w-full h-full object-cover opacity-50">
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
        <div class="relative max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-full flex flex-col justify-end items-start text-left pb-8 sm:pb-16 md:pb-24">
          <div class="w-full lg:w-2/3">
            <!-- 熱門度標籤 -->
            <div v-if="article.popularity_score" class="flex items-center mb-2 sm:mb-3">
              <span class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium bg-orange-500 text-white shadow-lg transform hover:scale-105 transition-all duration-300 animate-pulse">
                <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                熱門度 {{ Math.round(article.popularity_score) }}%
              </span>
            </div>
            
            <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-extrabold tracking-tight leading-tight mb-2 sm:mb-4">{{ article.title }}</h1>
            <p class="text-sm sm:text-base md:text-lg text-gray-300 mb-4 sm:mb-6 max-w-3xl line-clamp-2">{{ article.summary }}</p>
            
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
    </Slide>
    <template #addons>
      <Navigation />
      <Pagination />
    </template>
  </Carousel>
</template>

<style>
.carousel__pagination { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); }
.carousel__pagination-button::after { background-color: rgba(255, 255, 255, 0.5); border-radius: 50%; width: 12px; height: 12px; }
.carousel__pagination-button:hover::after, .carousel__pagination-button--active::after { background-color: white; }
.carousel__prev, .carousel__next { color: white; background-color: rgba(0, 0, 0, 0.3); border-radius: 50%; }
.carousel__prev:hover, .carousel__next:hover { color: white; background-color: rgba(0, 0, 0, 0.5); }

/* 熱門度標籤動畫 */
@keyframes popularityGlow {
  0%, 100% {
    box-shadow: 0 0 5px rgba(249, 115, 22, 0.5);
  }
  50% {
    box-shadow: 0 0 20px rgba(249, 115, 22, 0.8), 0 0 30px rgba(249, 115, 22, 0.6);
  }
}

.animate-pulse {
  animation: popularityGlow 2s ease-in-out infinite;
}

/* 輪播圖內容淡入動畫 */
.carousel__slide {
  opacity: 0;
  transition: opacity 0.5s ease-in-out;
}

.carousel__slide--active {
  opacity: 1;
}

.carousel__slide--active .relative > div {
  animation: slideContentFadeIn 0.8s ease-out 0.3s both;
}

@keyframes slideContentFadeIn {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>