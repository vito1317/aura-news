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
  <Carousel :items-to-show="1" :wrap-around="true" :autoplay="5000" class="bg-gray-800 text-white h-[450px] md:h-[550px]">
    <Slide v-for="article in articles" :key="article.id">
      <div class="relative w-full h-full">
        <img :src="article.image_url" :alt="article.title" class="absolute inset-0 w-full h-full object-cover opacity-50">
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex flex-col justify-end items-start text-left pb-16 sm:pb-24">
          <div class="w-full lg:w-2/3">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight leading-tight mb-4">{{ article.title }}</h1>
            <p class="text-lg text-gray-300 mb-6 max-w-3xl line-clamp-2">{{ article.summary }}</p>
            <RouterLink :to="{ name: 'article-detail', params: { id: article.id } }" class="inline-block bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-6 rounded-md transition-colors">閱讀更多</RouterLink>
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
</style>