<script setup>
import { RouterView, useRoute } from 'vue-router';
import { computed } from 'vue';
import TheHeader from './components/TheHeader.vue';
import TheFooter from './components/TheFooter.vue';
import { useHead } from '@vueuse/head';
const route = useRoute();
const isAdmin = computed(() => route.path.startsWith('/admin'));

useHead({
  title: 'Aura News',
  meta: [
    { name: 'description', content: 'Aura News - AI 新聞平台，最新、最即時的新聞內容。' }
  ]
});
</script>

<template>
  <div class="flex flex-col min-h-screen">
    <TheHeader v-if="!isAdmin" />
    
    <main class="flex-grow bg-white">
      <RouterView />
    </main>
    
    <TheFooter v-if="!isAdmin" />
  </div>
</template>