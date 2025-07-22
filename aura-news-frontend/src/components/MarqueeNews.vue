<script setup>
import { RouterLink } from 'vue-router';
import { computed, unref } from 'vue';
const props = defineProps({
  articles: {
    type: Array,
    required: true
  }
});
const randomHotArticles = computed(() => {
  const arr = unref(props.articles);
  const hot = Array.isArray(arr) ? arr.filter(a => Number(a.popularity_score) > 65) : [];
  for (let i = hot.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [hot[i], hot[j]] = [hot[j], hot[i]];
  }
  return hot;
});
console.log('MarqueeNews randomHotArticles:', randomHotArticles);
</script>

<template>
  <div v-if="randomHotArticles && randomHotArticles.length > 0" class="w-full marquee-outer">
    <div class="flex items-center h-11 sm:h-12 md:h-14">
      <span class="font-bold px-3 text-sm hidden sm:inline flex items-center marquee-title">
        <svg class="w-4 h-4 mr-1 text-yellow-300" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2.39 4.84 5.34.78-3.87 3.77.91 5.32L10 13.27l-4.77 2.51.91-5.32-3.87-3.77 5.34-.78L10 2z"/></svg>
        最新熱門：
      </span>
      <div class="marquee flex-1 overflow-hidden relative">
        <div class="marquee-content flex items-center" :style="{ animationDuration: `${Math.max((randomHotArticles?.length || 0) * 3, 8)}s` }">
          <template v-for="(article, idx) in randomHotArticles" :key="article.id">
            <RouterLink
              :to="{ name: 'article-detail', params: { id: article.id } }"
              class="mx-4 text-white hover:underline text-sm sm:text-base transition-colors marquee-link"
            >
              {{ article.title }}
            </RouterLink>
            <span v-if="randomHotArticles && idx !== randomHotArticles.length - 1" class="mx-2 opacity-60 marquee-sep">|</span>
          </template>
          
          <template v-for="(article, idx) in randomHotArticles" :key="'copy-' + article.id">
            <RouterLink
              :to="{ name: 'article-detail', params: { id: article.id } }"
              class="mx-4 text-white hover:underline text-sm sm:text-base transition-colors marquee-link"
            >
              {{ article.title }}
            </RouterLink>
            <span v-if="randomHotArticles && idx !== randomHotArticles.length - 1" class="mx-2 opacity-60 marquee-sep">|</span>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.marquee-outer {
  background: linear-gradient(90deg, #2563eb 0%, #1e40af 100%);
  border-bottom: 1.5px solid #1e3a8a;
  border-radius: 0;
  box-shadow: 0 2px 8px rgba(30,64,175,0.08);
  padding-left: 0.5rem;
  padding-right: 0.5rem;
  min-height: 2.75rem;
}
.marquee-title {
  color: #ffe066;
  letter-spacing: 0.5px;
  font-size: 1rem;
  display: flex;
  align-items: center;
  text-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
.marquee {
  position: relative;
  width: 100%;
  height: 2.5rem;
  overflow: hidden;
  display: flex;
  align-items: center;
}
.marquee-content {
  display: inline-flex;
  white-space: nowrap;
  will-change: transform;
  animation: marquee-scroll linear infinite;
  align-items: center;
  min-height: 2.5rem;
}
.marquee-link {
  font-weight: 500;
  padding: 0.1em 0.3em;
  border-radius: 6px;
  transition: background 0.2s, color 0.2s;
}
.marquee-link:hover {
  background: rgba(255,255,255,0.18);
  color: #ffe066;
  text-decoration: underline;
}
.marquee-sep {
  color: #ffe066;
  font-size: 1.1em;
  font-weight: bold;
  opacity: 0.7;
}
.marquee:hover .marquee-content {
  animation-play-state: paused;
}
@keyframes marquee-scroll {
  0% {
    transform: translateX(0%);
  }
  100% {
    transform: translateX(-50%);
  }
}
@media (max-width: 640px) {
  .marquee-outer {
    border-radius: 0;
    min-height: 2.2rem;
    padding-left: 0.2rem;
    padding-right: 0.2rem;
  }
  .marquee {
    height: 2.2rem;
  }
  .marquee-content {
    min-height: 2.2rem;
  }
  .marquee-title {
    font-size: 0.92rem;
  }
}
</style>
