<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { RouterLink } from 'vue-router';

const articles = ref([]);
const pagination = ref(null);
const isLoading = ref(true);
const error = ref(null);

const fetchArticles = async (url = '/api/admin/articles') => {
  isLoading.value = true;
  error.value = null;
  try {
    const response = await axios.get(url);
    articles.value = response.data.data;
    pagination.value = response.data; // 直接存整個 response
  } catch (err) {
    console.error("文章列表載入失敗:", err);
    error.value = '無法載入文章列表。';
  } finally {
    isLoading.value = false;
  }
};

const deleteArticle = async (articleId) => {
  if (!window.confirm('您確定要刪除這篇文章嗎？')) return;
  try {
    await axios.delete(`/api/admin/articles/${articleId}`);
    const currentPageUrl = pagination.value?.meta.path + '?page=' + pagination.value?.meta.current_page;
    fetchArticles(currentPageUrl);
    alert('文章已成功刪除！');
  } catch (err) {
    alert(err.response?.data?.message || '刪除文章失敗。');
  }
};

onMounted(fetchArticles);

const getStatusClass = (status) => {
  if (status === 1) return 'bg-green-100 text-green-800';
  if (status === 2) return 'bg-yellow-100 text-yellow-800';
  return 'bg-blue-100 text-blue-800';
};

const getStatusText = (status) => {
  if (status === 1) return '已發布';
  if (status === 2) return '草稿';
  return '待審核';
};
</script>

<template>
  <div>
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-2">
      <h1 class="text-2xl font-semibold text-gray-900">文章管理</h1>
      <RouterLink :to="{ name: 'admin-articles-create' }" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 px-4 rounded-lg w-full sm:w-auto text-center">＋ 新增文章</RouterLink>
    </div>

    
    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 sm:px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">標題</th>
            <th class="px-4 sm:px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">狀態</th>
            <th class="px-4 sm:px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">發布日期</th>
            <th class="px-4 sm:px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">操作</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-if="isLoading">
            <td colspan="4" class="text-center py-8 text-gray-500">載入中...</td>
          </tr>
          <tr v-else-if="error">
            <td colspan="4" class="text-center py-8 text-red-500">{{ error }}</td>
          </tr>
          <tr v-else-if="articles.length === 0">
            <td colspan="4" class="text-center py-8 text-gray-500">目前沒有任何文章。</td>
          </tr>
          <tr v-else v-for="article in articles" :key="article.id">
            <td class="px-4 sm:px-6 py-4 whitespace-nowrap font-medium text-gray-900 max-w-xs truncate">{{ article.title }}</td>
            <td class="px-4 sm:px-6 py-4">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getStatusClass(article.status)">
                {{ getStatusText(article.status) }}
              </span>
            </td>
            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-gray-500">{{ new Date(article.published_at).toLocaleDateString() }}</td>
            <td class="px-4 sm:px-6 py-4 whitespace-nowrap font-medium space-x-2 flex flex-col sm:flex-row gap-2">
              <RouterLink :to="{ name: 'admin-articles-edit', params: { id: article.id } }" class="text-indigo-600 hover:text-indigo-900">編輯</RouterLink>
              <button @click="deleteArticle(article.id)" class="text-red-600 hover:text-red-900">刪除</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination && pagination.links && articles.length > 0" class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-2">
      <span class="text-sm text-gray-700">
        顯示第 {{ pagination.from }} 到 {{ pagination.to }} 項，共 {{ pagination.total }} 項
      </span>
      <div class="flex items-center flex-wrap gap-1">
        <button
          v-for="link in pagination.links"
          :key="link.label"
          @click="fetchArticles(link.url)"
          :disabled="!link.url"
          class="px-3 py-1 text-sm rounded-md"
          :class="{
            'bg-brand-DEFAULT text-white': link.active,
            'bg-white text-gray-700 hover:bg-gray-50 border': !link.active && link.url,
            'text-gray-400 cursor-not-allowed border': !link.url
          }"
          v-html="link.label"
        ></button>
      </div>
    </div>
  </div>
</template>