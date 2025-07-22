<script setup>
import { RouterLink, RouterView } from 'vue-router';
import { ref } from 'vue';

const adminNavLinks = [
  { name: '儀表板總覽', path: '/admin/dashboard' },
  { name: '文章管理', path: '/admin/articles' },
  { name: '分類管理', path: '/admin/categories' },
  { name: '用戶管理', path: '/admin/users' },
  { name: '數據分析', path: '/admin/analytics' },
];

const sidebarOpen = ref(false);
const closeSidebar = () => { sidebarOpen.value = false; };
const openSidebar = () => { sidebarOpen.value = true; };
</script>

<template>
  <div class="flex h-screen bg-gray-100 font-sans">
    
    <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black bg-opacity-30 md:hidden" @click="closeSidebar"></div>
    
    <aside :class="[
      'fixed z-50 inset-y-0 left-0 w-64 bg-white border-r border-gray-200 flex flex-col transition-transform duration-200',
      sidebarOpen ? 'translate-x-0' : '-translate-x-full',
      'md:static md:translate-x-0 md:flex md:w-64 md:z-auto'
    ]" aria-label="後台選單">
      <div class="h-16 flex items-center justify-center border-b border-gray-200 flex-shrink-0">
        <h1 class="text-xl font-bold text-gray-800">Aura News 後台</h1>
      </div>
      <nav class="mt-6 flex-1">
        <RouterLink
          v-for="link in adminNavLinks"
          :key="link.name"
          :to="link.path"
          class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-50 hover:text-gray-900"
          active-class="bg-brand-light text-brand-dark font-semibold border-r-4 border-brand-dark"
        >
          <span class="mr-3 h-6 w-6">📄</span> 
          <span>{{ link.name }}</span>
        </RouterLink>
      </nav>
    </aside>

    
    <div class="flex-1 flex flex-col overflow-hidden">
      
      <div class="md:hidden flex items-center justify-between bg-white border-b border-gray-200 h-16 px-4">
        <button @click="openSidebar" aria-label="開啟選單" class="text-gray-700 focus:outline-none">
          <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <span class="text-lg font-bold">Aura News 後台</span>
        <div></div>
      </div>
      <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-4 sm:p-6 lg:p-8">
        <div class="flex justify-end items-center mb-6">
           <div class="flex items-center space-x-4">
            <span class="text-sm">你好, 管理員</span>
            <img class="h-10 w-10 rounded-full object-cover" src="https://i.pravatar.cc/150" alt="User avatar">
          </div>
        </div>
        <RouterView />
      </main>
    </div>
  </div>
</template>