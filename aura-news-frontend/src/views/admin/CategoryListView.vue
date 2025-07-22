<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const categories = ref([]);
const isLoading = ref(true);
const error = ref(null);
const editingCategory = ref(null);
const newCategory = ref({ name: '', slug: '' });
const isSubmitting = ref(false);
const formError = ref(null);

const fetchCategories = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    const res = await axios.get('/api/admin/categories');
    categories.value = Array.isArray(res.data) ? res.data : [];
  } catch (err) {
    error.value = '無法載入分類列表。';
    categories.value = [];
  } finally {
    isLoading.value = false;
  }
};

const addCategory = async () => {
  if (!newCategory.value.name.trim()) {
    formError.value = '請輸入分類名稱';
    return;
  }
  isSubmitting.value = true;
  formError.value = null;
  try {
    await axios.post('/api/admin/categories', newCategory.value);
    newCategory.value = { name: '', slug: '' };
    fetchCategories();
  } catch (err) {
    formError.value = '新增分類失敗';
  } finally {
    isSubmitting.value = false;
  }
};

const startEditing = (cat) => {
  editingCategory.value = { ...cat };
};
const cancelEditing = () => {
  editingCategory.value = null;
};
const saveCategory = async () => {
  if (!editingCategory.value.name.trim()) {
    formError.value = '請輸入分類名稱';
    return;
  }
  isSubmitting.value = true;
  formError.value = null;
  try {
    await axios.put(`/api/admin/categories/${editingCategory.value.slug}`, editingCategory.value);
    editingCategory.value = null;
    fetchCategories();
  } catch (err) {
    formError.value = '儲存失敗';
  } finally {
    isSubmitting.value = false;
  }
};
const deleteCategory = async (id) => {
  if (!window.confirm('確定要刪除此分類？')) return;
  isSubmitting.value = true;
  try {
    const cat = categories.value.find(c => c.id === id);
    if (!cat) {
      formError.value = '找不到分類';
      isSubmitting.value = false;
      return;
    }
    await axios.delete(`/api/admin/categories/${cat.slug}`);
    fetchCategories();
  } catch (err) {
    formError.value = '刪除失敗';
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(fetchCategories);
</script>
<template>
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">分類管理</h1>
    <form @submit.prevent="addCategory" class="bg-white p-4 sm:p-6 rounded-lg shadow mb-8 flex flex-col sm:flex-row gap-4 items-end">
      <div class="flex-1 w-full">
        <label class="block mb-2 font-medium">分類名稱</label>
        <input v-model="newCategory.name" type="text" class="w-full border rounded px-3 py-2" required />
      </div>
      <button type="submit" :disabled="isSubmitting" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2 px-6 rounded-lg w-full sm:w-auto">新增</button>
    </form>
    <div v-if="formError" class="text-red-500 mb-4">{{ formError }}</div>
    
    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 sm:px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">名稱</th>
            <th class="px-4 sm:px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Slug</th>
            <th class="px-4 sm:px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">操作</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-if="isLoading">
            <td colspan="3" class="text-center py-8 text-gray-400">載入中...</td>
          </tr>
          <tr v-else-if="error">
            <td colspan="3" class="text-center py-8 text-red-500">{{ error }}</td>
          </tr>
          <tr v-else-if="categories && categories.length === 0">
            <td colspan="3" class="text-center py-8 text-gray-400">目前沒有分類。</td>
          </tr>
          <tr v-else v-for="cat in categories" :key="cat.id">
            <td class="px-4 sm:px-6 py-4 whitespace-nowrap font-medium">
              <template v-if="editingCategory && editingCategory.id === cat.id">
                <input v-model="editingCategory.name" type="text" class="border rounded px-2 py-1 w-full" />
              </template>
              <template v-else>{{ cat.name }}</template>
            </td>
            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">{{ cat.slug }}</td>
            <td class="px-4 sm:px-6 py-4 whitespace-nowrap font-medium space-x-2 flex flex-col sm:flex-row gap-2">
              <template v-if="editingCategory && editingCategory.id === cat.id">
                <button @click="saveCategory" :disabled="isSubmitting" class="text-green-600 hover:text-green-900">儲存</button>
                <button @click="cancelEditing" class="text-gray-500 hover:text-gray-700">取消</button>
              </template>
              <template v-else>
                <button @click="startEditing(cat)" class="text-indigo-600 hover:text-indigo-900">編輯</button>
                <button @click="deleteCategory(cat.id)" :disabled="isSubmitting" class="text-red-600 hover:text-red-900">刪除</button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
