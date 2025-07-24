<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import Editor from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';

const props = defineProps({
  id: {
    type: String,
    default: null
  }
});

const isEditMode = computed(() => !!props.id);
const pageTitle = computed(() => isEditMode.value ? '編輯文章' : '新增文章');

const router = useRouter();
const form = ref({
  title: '',
  content: '',
  summary: '',
  status: 1,
  image_url: null,
  category_id: null,
  keywords: '',
  source_url: '',
});

const categories = ref([]);
const isSubmitting = ref(false);
const errors = ref({});
const editorInstance = ref(null);
const editorRef = ref(null);
const fileInputRef = ref(null);
const isUploadingImage = ref(false);

const mode = ref('manual');
const aiUrl = ref('');
const aiLoading = ref(false);
const aiError = ref('');

const triggerFileInput = () => {
  fileInputRef.value.click();
};

const handleImageUpload = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('image', file);

  isUploadingImage.value = true;
  try {
    const response = await axios.post(`/api/admin/images/upload`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    form.value.image_url = response.data.url;
  } catch (err) {
    alert('圖片上傳失敗，請檢查檔案大小或格式。');
    console.error(err);
  } finally {
    isUploadingImage.value = false;
  }
};

const initializeEditor = (initialContent = '') => {
  if (editorInstance.value) {
    editorInstance.value.destroy();
  }
  if (editorRef.value) {
    editorInstance.value = new Editor({
      el: editorRef.value,
      height: '500px',
      initialEditType: 'markdown',
      previewStyle: 'vertical',
      initialValue: initialContent,
      events: {
        change: () => {
          form.value.content = editorInstance.value.getMarkdown();
        }
      }
    });
  }
};

const fetchAiArticle = async () => {
  aiError.value = '';
  if (!aiUrl.value.trim()) {
    aiError.value = '請輸入新聞來源網址';
    return;
  }
  aiLoading.value = true;
  try {
    const res = await axios.post('/api/admin/articles/ai-generate', { url: aiUrl.value });
    Object.assign(form.value, res.data);
    initializeEditor(res.data.content || '');
    aiError.value = '';
  } catch (err) {
    aiError.value = err.response?.data?.message || 'AI 產生失敗，請確認網址正確且可公開存取';
  } finally {
    aiLoading.value = false;
  }
};

onMounted(async () => {
  try {
    const categoriesResponse = await axios.get(`/api/admin/categories`);
    categories.value = categoriesResponse.data;
  } catch (error) {
    console.error("無法載入分類:", error);
  }

  if (isEditMode.value) {
    try {
      const articleResponse = await axios.get(`/api/admin/articles/${props.id}`);
      form.value = articleResponse.data;
      initializeEditor(form.value.content);
    } catch (error) {
      alert('無法載入文章資料');
      router.push({ name: 'admin-articles' });
    }
  } else {
    initializeEditor();
  }
});

onUnmounted(() => {
  if (editorInstance.value) editorInstance.value.destroy();
});

const submitForm = async () => {
  isSubmitting.value = true;
  errors.value = {};
  try {
    if (isEditMode.value) {
      await axios.put(`/api/admin/articles/${props.id}`, form.value);
      alert('文章已成功更新！');
    } else {
      await axios.post(`/api/admin/articles`, form.value);
      alert('文章已成功建立！');
    }
    router.push({ name: 'admin-articles' });
  } catch (err) {
    if (err.response && err.response.status === 422) {
      errors.value = err.response.data.errors;
    } else {
      alert('發生未知錯誤，請稍後再試。');
    }
  } finally {
    isSubmitting.value = false;
  }
};

watch(mode, (newMode) => {
  if (newMode === 'manual') {
    initializeEditor(form.value.content || '');
  } else if (newMode === 'ai' && form.value.content) {
    initializeEditor(form.value.content);
  }
});
</script>

<template>
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 mb-6 sm:text-xl sm:mb-4">{{ pageTitle }}</h1>
    <div class="mb-6 flex gap-4 flex-wrap">
      <label class="inline-flex items-center">
        <input type="radio" value="manual" v-model="mode" class="form-radio">
        <span class="ml-2">手動建立</span>
      </label>
      <label class="inline-flex items-center">
        <input type="radio" value="ai" v-model="mode" class="form-radio">
        <span class="ml-2">AI 產生（輸入新聞網址）</span>
      </label>
    </div>
    <div v-if="mode === 'ai'" class="mb-8 p-6 bg-gray-50 rounded-lg border">
      <label class="block text-sm font-medium text-gray-700 mb-2">新聞來源網址</label>
      <div class="flex flex-col sm:flex-row gap-2">
        <input type="text" v-model="aiUrl" class="flex-1 border-gray-300 rounded-md shadow-sm min-w-0" placeholder="請貼上新聞網址">
        <button type="button" @click="fetchAiArticle" :disabled="aiLoading" class="bg-blue-600 hover:bg-blue-800 text-white font-bold px-5 py-2.5 rounded-lg shadow-lg text-base transition-all duration-150 disabled:opacity-50 border-2 border-blue-600 w-full sm:w-auto">{{ aiLoading ? '產生中...' : 'AI 產生' }}</button>
      </div>
      <p v-if="aiError" class="text-sm text-red-600 mt-2">{{ aiError }}</p>
      <p v-if="form.title && form.content" class="text-green-600 mt-2">AI 已產生內容，可直接編輯後儲存！</p>
    </div>
    <form v-show="mode === 'manual' || (mode === 'ai' && form.title && form.content)" @submit.prevent="submitForm" class="bg-white p-4 sm:p-8 rounded-lg shadow space-y-6 max-w-2xl w-full mx-auto">
      <div>
        <label for="title" class="block text-sm font-medium text-gray-700">文章標題</label>
        <input type="text" id="title" v-model="form.title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        <p v-if="errors.title" class="text-sm text-red-600 mt-1">{{ errors.title[0] }}</p>
      </div>
      <div>
        <label for="category" class="block text-sm font-medium text-gray-700">分類</label>
        <select id="category" v-model="form.category_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
          <option :value="null" disabled>-- 請選擇一個分類 --</option>
          <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
        </select>
        <p v-if="errors.category_id" class="text-sm text-red-600 mt-1">{{ errors.category_id[0] }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">特色圖片</label>
        <div class="mt-1 flex items-center justify-center w-full">
          <div class="w-full p-4 border-2 border-gray-300 border-dashed rounded-md">
            <div v-if="form.image_url" class="mb-4">
              <img :src="form.image_url" alt="Image Preview" class="w-full rounded-md shadow-md max-h-48 object-contain mx-auto">
            </div>
            <div class="flex justify-center">
              <div v-if="isUploadingImage" class="text-gray-500">上傳中...</div>
              <button v-else type="button" @click="triggerFileInput" class="bg-blue-600 hover:bg-blue-800 text-white px-5 py-2.5 rounded-lg font-bold shadow border-2 border-blue-600 transition-all duration-150 w-full sm:w-auto">{{ form.image_url ? '更換圖片' : '選擇圖片' }}</button>
            </div>
            <input type="file" ref="fileInputRef" @change="handleImageUpload" class="hidden" accept="image/*">
          </div>
        </div>
        <p v-if="errors.image_url" class="text-sm text-red-600 mt-1">{{ errors.image_url[0] }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">文章內容 (Markdown)</label>
        <div ref="editorRef" class="mt-1 overflow-x-auto"></div>
        <p v-if="errors.content" class="text-sm text-red-600 mt-1">{{ errors.content[0] }}</p>
      </div>
      <div>
        <label for="summary" class="block text-sm font-medium text-gray-700">摘要 (選填)</label>
        <input type="text" id="summary" v-model="form.summary" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
      </div>
      <div>
        <label for="source_url" class="block text-sm font-medium text-gray-700">原文出處 (選填)</label>
        <input type="text" id="source_url" v-model="form.source_url" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" placeholder="https://example.com/news/123">
      </div>
      <div>
        <label for="status" class="block text-sm font-medium text-gray-700">狀態</label>
        <select id="status" v-model="form.status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
          <option :value="1">已發布</option>
          <option :value="2">草稿</option>
          <option :value="3">待審核</option>
        </select>
      </div>
      <div>
        <label for="keywords" class="block text-sm font-medium text-gray-700">關鍵字 (逗號分隔)</label>
        <input type="text" id="keywords" v-model="form.keywords" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
      </div>
      <div class="text-right">
        <button type="submit" :disabled="isSubmitting" class="bg-blue-600 hover:bg-blue-800 text-white font-bold py-2.5 px-8 rounded-lg shadow-lg text-lg transition-all duration-150 disabled:opacity-50 border-2 border-blue-600 w-full sm:w-auto">{{ isSubmitting ? '儲存中...' : '儲存文章' }}</button>
      </div>
    </form>
  </div>
</template>