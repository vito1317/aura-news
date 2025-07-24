<template>
    <footer class="bg-gray-100 border-t border-gray-200 py-6 mt-8">
      <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-500">
        <div class="mb-2 sm:mb-0">© {{ new Date().getFullYear() }} Aura News. All rights reserved.</div>
        <div class="flex space-x-4">
          <RouterLink to="/terms" class="hover:underline">服務條款</RouterLink>
          <RouterLink to="/privacy" class="hover:underline">隱私權政策</RouterLink>
        </div>
      </div>
    </footer>
  </template>