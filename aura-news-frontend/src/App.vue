<script setup>
import { RouterView, useRoute } from 'vue-router';
import { computed } from 'vue';
import TheHeader from './components/TheHeader.vue';
import TheFooter from './components/TheFooter.vue';
const route = useRoute();
const isAdmin = computed(() => route.path.startsWith('/admin'));
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