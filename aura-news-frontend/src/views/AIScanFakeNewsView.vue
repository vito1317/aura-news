<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { marked } from 'marked';
import { useHead } from '@vueuse/head';
import html2canvas from 'html2canvas';
import QRCode from 'qrcode';

const renderer = {
  link(href, title, text) {
    href = typeof href === 'string' ? href : '';
    text = typeof text === 'string' ? text : '';
    let cleanHref = href.replace(/[),。！？\]\[\s]+$/g, '');
    let cleanText = text.replace(/[),。！？\]\[\s]+$/g, '');
    cleanHref = cleanHref.split(' ')[0];
    cleanText = cleanText.split(' ')[0];
    if (!/^https?:\/\//.test(cleanHref)) {
      cleanHref = 'http://' + cleanHref;
    }
    return `<a href="${cleanHref}" target="_blank" rel="noopener noreferrer">${cleanText}</a>`;
  }
};
marked.use({ renderer });

const input = ref('');
const isLoading = ref(false);
const result = ref(null);
const error = ref(null);
const progress = ref('');
let pollingInterval = null;

const resultSectionRef = ref(null);
const isResultSectionVisible = ref(false);
const hasAnimationStarted = ref(false);
const isSavingImage = ref(false);
const showPreview = ref(false);
const previewImageUrl = ref('');
const qrCodeUrl = ref('');
const currentTaskId = ref(null);
const isQueued = ref(false);
const isVerifiedContentExpanded = ref(false);
const showShareSuccess = ref(false);

const steps = computed(() => {
  if (/^https?:\/\//.test(input.value.trim())) {
    return [
      '分析網址',
      'AI 偵測內容類型',
      'AI 產生搜尋關鍵字',
      '新聞資料搜尋',
      'AI 綜合查證',
      '完成',
    ];
  } else {
    return [
      '分析內容',
      'AI 偵測內容類型',
      'AI 產生搜尋關鍵字',
      '新聞資料搜尋',
      'AI 綜合查證',
      '完成',
    ];
  }
});
const currentStep = computed(() => {
  const p = progress.value || '';
  if (p === '完成') return steps.value.length - 1;
  if (p.includes('AI 綜合查證')) return 4;
  if (p.includes('新聞資料') || p.includes('站內搜尋')) return 3;
  if (p.includes('AI 產生搜尋關鍵字')) return 2;
  if (p.includes('AI 偵測內容類型')) return 1;
  if (p.includes('抓取主文') || p.includes('查看新聞') || p.includes('分析網址') || p.includes('分析內容')) return 0;
  return 0;
});

const confidence = computed(() => {
  if (!result.value?.result) return null;
  const match = result.value.result.match(/【可信度：(\d+)%?】/);
  return match ? parseInt(match[1], 10) : null;
});
const confidenceColor = computed(() => {
  if (confidence.value === null) return '#d1d5db';
  if (confidence.value >= 80) return '#22c55e';
  if (confidence.value >= 60) return '#eab308';
  if (confidence.value >= 40) return '#f97316';
  return '#ef4444';
});
const resultWithoutConfidence = computed(() => {
  if (!result.value?.result) return '';
  return result.value.result.replace(/【可信度：.+?】\s*/g, '');
});

const sources = computed(() => {
  if (!result.value?.result) return '';
  const match = result.value.result.match(/【查證出處】([\s\S]*)$/);
  return match ? match[1].trim() : '';
});
const resultWithoutConfidenceAndSources = computed(() => {
  let text = result.value?.result || '';
  text = text.replace(/【可信度：.+?】\s*/g, '');
  text = text.replace(/【查證出處】([\s\S]*)$/, '');
  return text;
});

function linkify(text) {
  if (!text) return '';
  text = String(text);
  return text.replace(/(https?:\/\/[\w\-\.\/?#=&%+:;@,~]+)(?=[\s\n\r\)\]\}。，！？；：]|$)/g, (url) => {
    let cleanUrl = url.replace(/[),。！？；：\]\[\s]+$/g, '');
    return `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer">${cleanUrl}</a>`;
  });
}

const scanFakeNews = async () => {
  if (!input.value.trim()) {
    error.value = '請輸入新聞內容或網址';
    return;
  }
  isLoading.value = true;
  result.value = null;
  error.value = null;
  progress.value = '';
  
  isResultSectionVisible.value = false;
  hasAnimationStarted.value = false;
  isQueued.value = false;
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
  try {
    const res = await axios.post('/api/ai/scan-fake-news/start', { content: input.value });
    if (res.data && res.data.error) {
      error.value = res.data.error;
      isLoading.value = false;
      return;
    }
    const taskId = res.data.taskId;
    currentTaskId.value = taskId;
    pollProgress(taskId);
  } catch (err) {
    error.value = err.response?.data?.error || err.response?.data?.message || 'AI 掃描失敗，請稍後再試';
    isLoading.value = false;
  }
};

const pollProgress = (taskId) => {
  pollingInterval = setInterval(async () => {
    try {
      const res = await axios.get(`/api/ai/scan-fake-news/progress/${taskId}`);
      progress.value = res.data.progress;
      
      // 檢查是否排隊中
      if (res.data.isQueued) {
        isQueued.value = true;
      }
      
      if (res.data.error === '此內容非新聞或文章，請確認輸入') {
        error.value = res.data.error;
        isLoading.value = false;
        clearInterval(pollingInterval);
        pollingInterval = null;
        return;
      }
      if (
        res.data.progress === '主文擷取失敗，請嘗試複製主文內容貼上' ||
        res.data.progress === '請求過於頻繁，請稍後再試'
      ) {
        error.value = res.data.progress;
        isLoading.value = false;
        clearInterval(pollingInterval);
        pollingInterval = null;
        return;
      }
      if (res.data.progress === '完成') {
        result.value = {
          result: res.data.result,
          verified_content: res.data.verified_content,
          original_content: res.data.original_content,
        };
        isLoading.value = false;
        clearInterval(pollingInterval);
        pollingInterval = null;
        
        nextTick(() => {
          setupAnimationWhenResultAvailable();
        });
        // 查證完成後自動更新次數
        fetchUsageCount();
      }
      
      if (res.data.error) {
        error.value = res.data.error;
        isLoading.value = false;
        clearInterval(pollingInterval);
        pollingInterval = null;
        return;
      }
    } catch (err) {
      if (err.response && err.response.status === 404) {
        return;
      }
      error.value = '連線中斷，請重試';
      isLoading.value = false;
      clearInterval(pollingInterval);
      pollingInterval = null;
    }
  }, 1500);
};

const setupResultAnimationObserver = () => {
  if (!resultSectionRef.value) {
    return;
  }
  
  const triggerAnimation = () => {
    if (hasAnimationStarted.value) return;
    
    hasAnimationStarted.value = true;
    
    setTimeout(() => {
      isResultSectionVisible.value = true;
    }, 300);
  };
  
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting && !hasAnimationStarted.value) {
          triggerAnimation();
        }
      });
    },
    {
      threshold: 0.1,
      rootMargin: '0px 0px -150px 0px'
    }
  );
  
  observer.observe(resultSectionRef.value);
  
  const handleScroll = () => {
    if (hasAnimationStarted.value) return;
    
    const rect = resultSectionRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    
    if (rect.top < windowHeight - 200 && rect.bottom > 0) {
      triggerAnimation();
      window.removeEventListener('scroll', handleScroll);
    }
  };
  
  window.addEventListener('scroll', handleScroll);
  
  setTimeout(() => {
    const rect = resultSectionRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    
    if (rect.top < windowHeight - 200 && rect.bottom > 0 && !hasAnimationStarted.value) {
      triggerAnimation();
    }
  }, 500);
  
  onUnmounted(() => {
    observer.disconnect();
    window.removeEventListener('scroll', handleScroll);
  });
};

const setupAnimationWhenResultAvailable = () => {
  if (result.value && resultSectionRef.value && !hasAnimationStarted.value) {
    setupResultAnimationObserver();
  }
};

// 檢查 URL 參數並載入結果
const loadResultFromUrl = async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const taskId = urlParams.get('task_id');
  
  if (taskId) {
    try {
      isLoading.value = true;
      const res = await axios.get(`/api/ai/scan-fake-news/result/${taskId}`);
      
      if (res.data.success) {
        const data = res.data.data;
        result.value = {
          result: data.analysis_result,
          verified_content: data.verified_content,
          original_content: data.original_content,
        };
        currentTaskId.value = data.task_id;
        
        // 觸發動畫
        nextTick(() => {
          setupAnimationWhenResultAvailable();
        });
      }
    } catch (err) {
      console.error('載入結果失敗:', err);
      error.value = err.response?.data?.message || '載入結果失敗';
    } finally {
      isLoading.value = false;
    }
  }
};

// 生成預覽圖片
const generatePreviewImage = async () => {
  if (!resultSectionRef.value) return;
  
  isSavingImage.value = true;
  
  try {
    // 暫時禁用動畫，確保圖片生成時內容穩定
    const originalVisibility = isResultSectionVisible.value;
    const originalAnimation = hasAnimationStarted.value;
    
    // 強制顯示結果區域，禁用動畫
    isResultSectionVisible.value = true;
    hasAnimationStarted.value = true;
    
    // 等待 DOM 更新
    await nextTick();
    
    // 只禁用特定動畫，保留定位
    const style = document.createElement('style');
    style.id = 'disable-animations';
    style.textContent = `
      .confidence-icon,
      .progress-animation,
      .score-animation,
      .level-animation,
      .result-animation,
      .analysis-animation,
      .sources-animation {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
      }
    `;
    document.head.appendChild(style);
    
    // 生成包含 task_id 的 URL
    const baseUrl = window.location.origin + window.location.pathname;
    const qrUrl = currentTaskId.value ? `${baseUrl}?task_id=${currentTaskId.value}` : window.location.href;
    console.log('生成 QR Code 的 URL:', qrUrl);
    
    // 先生成 QR Code
    let qrCodeDataUrl;
    try {
      qrCodeDataUrl = await QRCode.toDataURL(qrUrl, {
        width: 80,
        margin: 1,
        color: {
          dark: '#000000',
          light: '#FFFFFF'
        },
        errorCorrectionLevel: 'M'
      });
      console.log('QR Code 生成成功');
    } catch (qrError) {
      console.error('QR Code 生成失敗:', qrError);
      // 如果 QR Code 生成失敗，使用預設的 URL
      qrCodeDataUrl = await QRCode.toDataURL('https://aura-news.com', {
        width: 80,
        margin: 1,
        color: {
          dark: '#000000',
          light: '#FFFFFF'
        }
      });
    }
    
    // 在 DOM 中添加 QR Code 元素
    const qrContainer = document.createElement('div');
    qrContainer.style.position = 'absolute';
    qrContainer.style.bottom = '25px';
    qrContainer.style.right = '20px';
    qrContainer.style.zIndex = '1000';
    qrContainer.style.backgroundColor = '#ffffff';
    qrContainer.style.padding = '10px';
    qrContainer.style.borderRadius = '8px';
    qrContainer.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    qrContainer.style.transform = 'none'; // 確保沒有變形
    qrContainer.style.animation = 'none'; // 確保沒有動畫
    
    const qrImg = document.createElement('img');
    qrImg.src = qrCodeDataUrl;
    qrImg.style.width = '80px';
    qrImg.style.height = '80px';
    qrImg.style.display = 'block';
    
    const qrText = document.createElement('div');
    qrText.textContent = '掃描查看';
    qrText.style.fontSize = '12px';
    qrText.style.textAlign = 'center';
    qrText.style.marginTop = '5px';
    qrText.style.color = '#666666';
    
    qrContainer.appendChild(qrImg);
    qrContainer.appendChild(qrText);
    
    // 確保 resultSectionRef 有相對定位
    if (resultSectionRef.value.style.position !== 'relative') {
      resultSectionRef.value.style.position = 'relative';
    }
    
    resultSectionRef.value.appendChild(qrContainer);
    
    console.log('QR Code 元素已添加到 DOM');
    console.log('QR Code Data URL 長度:', qrCodeDataUrl.length);
    
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    const canvas = await html2canvas(resultSectionRef.value, {
      backgroundColor: '#ffffff',
      scale: 1.5,
      useCORS: true,
      allowTaint: true,
      width: resultSectionRef.value.offsetWidth,
      height: resultSectionRef.value.offsetHeight,
      scrollX: 0,
      scrollY: 0,
      windowWidth: resultSectionRef.value.offsetWidth,
      windowHeight: resultSectionRef.value.offsetHeight,
      logging: false,
      removeContainer: true,
    });
    
    previewImageUrl.value = canvas.toDataURL('image/png');
    qrCodeUrl.value = qrCodeDataUrl;
    showPreview.value = true;
    
    // 移除 QR Code 元素
    if (qrContainer && qrContainer.parentNode) {
      qrContainer.parentNode.removeChild(qrContainer);
    }
    
    // 移除禁用動畫的樣式
    const disableStyle = document.getElementById('disable-animations');
    if (disableStyle) {
      disableStyle.remove();
    }
    
    // 恢復動畫狀態
    isResultSectionVisible.value = originalVisibility;
    hasAnimationStarted.value = originalAnimation;
    
  } catch (error) {
    console.error('生成預覽圖片失敗:', error);
    alert('生成預覽圖片失敗，請稍後再試');
    
    // 移除禁用動畫的樣式
    const disableStyle = document.getElementById('disable-animations');
    if (disableStyle) {
      disableStyle.remove();
    }
    
    // 錯誤時也要恢復動畫狀態
    isResultSectionVisible.value = originalVisibility;
    hasAnimationStarted.value = originalAnimation;
  } finally {
    isSavingImage.value = false;
  }
};

const saveResultAsImage = async () => {
  if (!previewImageUrl.value) {
    await generatePreviewImage();
    return;
  }
  
  try {
    const link = document.createElement('a');
    link.download = `AI查證結果_${new Date().toISOString().slice(0, 10)}.png`;
    link.href = previewImageUrl.value;
    link.click();
  } catch (error) {
    console.error('儲存圖片失敗:', error);
    alert('儲存圖片失敗，請稍後再試');
  }
};

const shareResult = async () => {
  if (!previewImageUrl.value) {
    await generatePreviewImage();
    return;
  }
  
  try {
    const response = await fetch(previewImageUrl.value);
    const blob = await response.blob();
    const file = new File([blob], `AI查證結果_${new Date().toISOString().slice(0, 10)}.png`, { type: 'image/png' });
    
    if (navigator.share) {
      await navigator.share({
        title: 'AI 假新聞查證結果',
        text: `可信度: ${confidence.value}% - ${input.value.slice(0, 50)}...`,
        files: [file],
      });
    } else {
      saveResultAsImage();
    }
  } catch (error) {
    console.error('分享失敗:', error);
    saveResultAsImage();
  }
};

// 加上分享連結函數：
const shareResultLink = async () => {
  const baseUrl = window.location.origin + window.location.pathname;
  const shareUrl = currentTaskId.value ? `${baseUrl}?task_id=${currentTaskId.value}` : window.location.href;
  
  try {
    if (navigator.share) {
      // 使用 Web Share API
      await navigator.share({
        title: 'AI 假新聞查證結果',
        text: `可信度: ${confidence.value}% - ${input.value.slice(0, 50)}...`,
        url: shareUrl,
      });
    } else {
      // 複製連結到剪貼簿
      await navigator.clipboard.writeText(shareUrl);
      showShareSuccess.value = true;
      setTimeout(() => {
        showShareSuccess.value = false;
      }, 2000);
    }
  } catch (error) {
    console.error('分享失敗:', error);
    // 如果剪貼簿 API 失敗，使用傳統方法
    try {
      const textArea = document.createElement('textarea');
      textArea.value = shareUrl;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      
      showShareSuccess.value = true;
      setTimeout(() => {
        showShareSuccess.value = false;
      }, 2000);
    } catch (fallbackError) {
      console.error('複製連結失敗:', fallbackError);
      alert('無法分享連結，請手動複製網址');
    }
  }
};

// 使用次數統計
const usageCount = ref({ total: 0, today: 0 });
const fetchUsageCount = async () => {
  try {
    const res = await axios.get('/api/ai/scan-fake-news/usage-count');
    usageCount.value = res.data;
  } catch (e) {
    usageCount.value = { total: 0, today: 0 };
  }
};

// 組件掛載時檢查 URL 參數
onMounted(() => {
  loadResultFromUrl();
  fetchUsageCount();
});

useHead({
  title: 'AI 假新聞查證｜Aura News - 即時可信度分析',
  meta: [
    { name: 'description', content: 'Aura News AI 假新聞查證工具，支援網址或主文內容輸入，AI 即時分析可信度、查證出處，提供最即時的新聞真偽判斷。' },
    { property: 'og:title', content: 'AI 假新聞查證｜Aura News - 即時可信度分析' },
    { property: 'og:description', content: 'Aura News AI 假新聞查證工具，支援網址或主文內容輸入，AI 即時分析可信度、查證出處，提供最即時的新聞真偽判斷。' },
    { property: 'og:type', content: 'website' },
    { property: 'og:image', content: '/aura-news.png' },
    { property: 'og:url', content: typeof window !== 'undefined' ? window.location.href : '' },
    { name: 'twitter:card', content: 'summary_large_image' }
  ]
});

const enter = (el) => {
  const height = el.scrollHeight;
  el.style.height = '0px';
  el.offsetHeight; // 強制重繪
  el.style.height = height + 'px';
};

const leave = (el) => {
  el.style.height = el.scrollHeight + 'px';
  el.offsetHeight; // 強制重繪
  el.style.height = '0px';
};
</script>

<template>
  <div class="max-w-2xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <!-- 使用次數統計 -->
    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between text-sm text-gray-500">
      <div>
        <span class="font-bold text-blue-700">今日查證：</span>
        <span>{{ usageCount.today }}</span>
        <span class="ml-2">次</span>
        <span class="ml-2 text-xs text-gray-400" title="包含AI查證與站內新聞文章">(含AI+文章)</span>
      </div>
      <div class="mt-1 sm:mt-0">
        <span class="font-bold text-blue-700">累積查證：</span>
        <span>{{ usageCount.total }}</span>
        <span class="ml-2">次</span>
        <span class="ml-2 text-xs text-gray-400" title="包含AI查證與站內新聞文章">(含AI+文章)</span>
      </div>
    </div>
    <div class="mb-8 text-center">
      <h1 class="text-3xl font-extrabold text-blue-800 mb-2">AI 假新聞即時掃描</h1>
      <p class="text-gray-600">輸入新聞內容或網址，AI 將自動查證並給出可信度與建議。</p>
    </div>
    <div class="bg-white rounded-xl shadow p-6 mb-6">
      <label class="block text-gray-700 font-semibold mb-2" for="news-input">新聞內容或網址</label>
      <textarea id="news-input" v-model="input" rows="6" class="w-full border-2 border-blue-200 focus:border-blue-500 rounded-lg p-3 transition" placeholder="請貼上新聞內容或網址..." :disabled="isLoading"></textarea>
      <button @click="scanFakeNews" :disabled="isLoading" class="mt-4 w-full flex justify-center items-center bg-gradient-to-r from-blue-600 to-blue-400 hover:from-blue-700 hover:to-blue-500 text-white font-bold py-2.5 rounded-lg disabled:opacity-50 transition">
        <svg v-if="isLoading" class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
        {{ isLoading ? 'AI 掃描中...' : '開始掃描' }}
      </button>
      <transition name="fade">
        <div v-if="error" class="text-red-600 mt-4 text-center font-bold">
          <template v-if="error === '此內容非新聞或文章，請確認輸入'">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
              <strong class="font-bold">非新聞內容：</strong>
              <span class="block sm:inline">請輸入完整的新聞內容或正確的新聞網址。</span>
            </div>
          </template>
          <template v-else>
            {{ error }}
          </template>
        </div>
      </transition>
    </div>

    <div v-if="isLoading || (progress && progress !== '完成')" class="mb-8">
      <div class="flex items-center justify-between mb-2">
        <span class="text-blue-700 font-semibold">AI 掃描進度</span>
        <span class="text-sm text-gray-500">{{ progress }}</span>
      </div>
      
      <!-- 排隊中提示 -->
      <div v-if="isQueued" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="flex items-center">
          <svg class="h-5 w-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span class="text-yellow-800 font-medium">系統繁忙，您的請求正在排隊中，請稍後...</span>
        </div>
        <p class="text-yellow-700 text-sm mt-1">我們會盡快處理您的請求，請保持頁面開啟。</p>
      </div>
              <div class="flex items-start justify-between">
          <template v-for="(step, idx) in steps" :key="step">
            <div class="flex flex-col items-center flex-1">
              <div
                :class="[
                  'w-8 h-8 rounded-full flex items-center justify-center font-bold mb-3',
                  idx < currentStep ? 'bg-blue-500 text-white' : idx === currentStep ? 'bg-blue-200 text-blue-800 border-2 border-blue-500' : 'bg-gray-200 text-gray-400'
                ]"
              >
                {{ idx + 1 }}
              </div>
              <span
                :class="[
                  'text-xs',
                  'h-10',
                  'flex items-center justify-center',
                  'text-center',
                  'leading-tight',
                  'px-1',
                  idx <= currentStep ? 'text-blue-700 font-semibold' : 'text-gray-400'
                ]"
                style="min-height: 2.5rem;"
              >
                {{ step }}
              </span>
            </div>
            <div
              v-if="idx < steps.length - 1"
              class="flex-1 flex items-center justify-center"
              style="height: 1.5rem; margin-top: 1.5rem;"
            >
              <div class="w-full h-1 bg-gradient-to-r from-blue-200 to-blue-400 mx-1"></div>
            </div>
          </template>
        </div>
    </div>

    <transition name="fade">
      <div 
        v-if="result" 
        ref="resultSectionRef"
        class="mt-8 p-8 bg-gradient-to-br from-blue-50 to-white rounded-2xl border border-blue-100 shadow"
        :class="{ 'result-animation': isResultSectionVisible }"
      >
                  <div class="flex items-center justify-between mb-4">
            <div class="flex items-center">
              <svg class="h-8 w-8 text-green-500 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2l4-4m5 2a9 9 0 11-18 0a9 9 0 0118 0z"/>
              </svg>
              <h2 class="text-xl font-bold text-blue-800">AI 判斷結果</h2>
            </div>
            <div class="flex space-x-2">
              <button
                @click="shareResultLink"
                class="flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors"
              >
                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"/>
                </svg>
                分享連結
              </button>
              <button
                @click="generatePreviewImage"
                :disabled="isSavingImage"
                class="flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
              >
                <svg v-if="isSavingImage" class="animate-spin h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <svg v-else class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                {{ isSavingImage ? '生成中...' : '預覽圖片' }}
              </button>
            </div>
          </div>
        <div v-if="confidence !== null" class="mb-4 flex flex-col items-center justify-center" style="height:200px; margin-bottom: 0; margin-top: 2rem;">
          <div 
            class="relative" 
            :class="{ 'confidence-icon': isResultSectionVisible }" 
            style="width: 170px; height: 170px;"
          >
          <svg :width="170" :height="170" viewBox="0 0 170 170" style="display:block;">
            <circle cx="85" cy="85" r="80" fill="#f3f4f6" />
            <circle
              :stroke="confidenceColor"
              stroke-width="14"
              fill="none"
              cx="85" cy="85" r="75"
              :stroke-dasharray="2 * Math.PI * 75"
                :stroke-dashoffset="isResultSectionVisible ? 2 * Math.PI * 75 * (1 - confidence / 100) : 2 * Math.PI * 75"
                :style="{ '--final-offset': 2 * Math.PI * 75 * (1 - confidence / 100) + 'px' }"
                :class="{ 'progress-animation': isResultSectionVisible }"
              stroke-linecap="round"
              transform="rotate(-90 85 85)"
            />
              <text 
                x="85" y="85" 
                text-anchor="middle" 
                font-size="44" 
                font-weight="bold" 
                :fill="confidenceColor" 
                dominant-baseline="middle" 
                alignment-baseline="middle" 
                dy=".1em"
                :class="{ 'score-animation': isResultSectionVisible }"
                :style="{ opacity: isResultSectionVisible ? 'inherit' : '0', transform: isResultSectionVisible ? 'inherit' : 'scale(0.8)' }"
              >
                {{ confidence !== null ? confidence + '%' : '' }}
              </text>
          </svg>
          </div>
          <span 
            class="text-lg font-bold text-gray-700" 
            style="margin-top: 40px;"
            :class="{ 'level-animation': isResultSectionVisible }"
            :style="{ 
              marginTop: '40px',
              opacity: isResultSectionVisible ? 'inherit' : '0', 
              transform: isResultSectionVisible ? 'inherit' : 'translateY(20px)' 
            }"
          >
            AI 可信度
          </span>
        </div>
        <div 
          class="text-gray-800 leading-relaxed text-base prose prose-blue max-w-none" 
          v-html="marked(resultWithoutConfidenceAndSources)" 
          style="word-break: break-all; overflow-wrap: anywhere; margin-top: 2rem;"
          :class="{ 'analysis-animation': isResultSectionVisible }"
          :style="{ 
            opacity: isResultSectionVisible ? 'inherit' : '0', 
            transform: isResultSectionVisible ? 'inherit' : 'translateY(30px)' 
          }"
        ></div>
        <div 
          v-if="sources" 
          class="mt-6 p-4 rounded-lg bg-blue-50 border-l-4 border-blue-400 text-blue-900 text-sm whitespace-pre-line" 
          style="word-break: break-all; overflow-wrap: anywhere;"
          :class="{ 'sources-animation': isResultSectionVisible }"
          :style="{ 
            opacity: isResultSectionVisible ? 'inherit' : '0', 
            transform: isResultSectionVisible ? 'inherit' : 'translateY(30px)' 
          }"
        >
          <strong class="block mb-1 text-blue-700">查證出處</strong>
          <span v-html="linkify(sources)"></span>
        </div>
        <div v-if="result.verified_content" class="mt-6 p-4 rounded-lg bg-gray-50 border-l-4 border-gray-300 text-gray-800 text-sm">
  <div class="flex items-center justify-between mb-2">
    <strong class="text-gray-700">查證內文</strong>
    <button
      @click="isVerifiedContentExpanded = !isVerifiedContentExpanded"
      class="flex items-center text-blue-600 hover:text-blue-800 text-xs font-medium transition-colors"
    >
      <svg
        class="w-4 h-4 mr-1 transition-transform"
        :class="{ 'rotate-180': isVerifiedContentExpanded }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
      {{ isVerifiedContentExpanded ? '收合' : '展開' }}
    </button>
  </div>
  <transition
    name="expand"
    @enter="enter"
    @leave="leave"
  >
    <div
      v-show="isVerifiedContentExpanded"
      class="overflow-hidden"
    >
      <div class="whitespace-pre-line" style="word-break: break-all; overflow-wrap: anywhere;">
        {{ result.verified_content }}
      </div>
    </div>
  </transition>
  <div v-if="!isVerifiedContentExpanded" class="text-gray-500 text-xs">
    點擊展開查看完整查證內文
  </div>
</div>
      </div>
    </transition>

    <!-- 預覽模態框 -->
    <transition name="modal">
      <div v-if="showPreview" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl">
          <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-900">預覽圖片</h3>
            <button
              @click="showPreview = false"
              class="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="p-6 overflow-auto max-h-[calc(90vh-120px)]">
            <div class="text-center mb-4">
              <p class="text-gray-600 text-sm">圖片已包含 QR Code，掃描可重新訪問此頁面</p>
            </div>
            <div class="flex justify-center">
              <img 
                :src="previewImageUrl" 
                alt="AI 查證結果預覽" 
                class="max-w-full h-auto rounded-lg shadow-lg border border-gray-200"
                style="max-height: 70vh;"
              />
            </div>
            <div class="flex justify-center space-x-4 mt-6">
              <button
                @click="shareResult"
                class="flex items-center px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors"
              >
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 8a3 3 0 11-6 0 3 3 0 016 0zm6 8a3 3 0 11-6 0 3 3 0 016 0zm-6 0v-4m0 0V8m0 4h-4m4 0h4" />
                </svg>
                分享圖片
              </button>
              <button
                @click="saveResultAsImage"
                class="flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors"
              >
                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                下載圖片
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- 分享成功提示 -->
    <transition name="fade">
      <div v-if="showShareSuccess" class="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50">
        <div class="flex items-center">
          <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          連結已複製到剪貼簿
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

/* 可信度圖標動畫效果 */
@keyframes credibilityPulse {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.05);
    opacity: 0.8;
  }
}

.confidence-icon {
  animation: credibilityPulse 2s ease-in-out infinite;
}

/* 圓形進度條動畫 */
@keyframes progressFill {
  from {
    stroke-dashoffset: 471; /* 2 * Math.PI * 75 */
  }
  to {
    stroke-dashoffset: var(--final-offset, 0);
  }
}

.progress-animation {
  animation: progressFill 1.5s ease-out forwards;
}

/* 確保進度條在沒有動畫時也能正常顯示 */
circle[class*="progress"]:not(.progress-animation) {
  stroke-dashoffset: 0;
}

/* 分數文字動畫 */
@keyframes scoreFadeIn {
  from {
    opacity: 0;
    transform: scale(0.8);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.score-animation {
  animation: scoreFadeIn 0.6s ease-out 1s forwards;
  opacity: 0;
  transform: scale(0.8);
}

/* 確保 SVG 文字元素在沒有動畫時也能正常顯示 */
text:not(.score-animation) {
  opacity: 1;
  transform: scale(1);
}

/* 等級標籤動畫 */
@keyframes levelSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.level-animation {
  animation: levelSlideIn 0.6s ease-out 1.2s forwards;
  opacity: 0;
  transform: translateY(20px);
}

/* 確保等級標籤在沒有動畫時也能正常顯示 */
span[class*="level"]:not(.level-animation) {
  opacity: 1;
  transform: translateY(0);
}

/* 結果區塊動畫 */
@keyframes resultFadeIn {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.result-animation {
  animation: resultFadeIn 0.8s ease-out forwards;
  opacity: 0;
  transform: translateY(30px);
}

/* 確保結果區塊在沒有動畫時也能正常顯示 */
div[class*="result"]:not(.result-animation) {
  opacity: 1;
  transform: translateY(0);
}

/* 分析內容動畫 */
@keyframes analysisFadeIn {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.analysis-animation {
  animation: analysisFadeIn 0.8s ease-out 1.5s forwards;
  opacity: 0;
  transform: translateY(30px);
}

/* 確保分析內容在沒有動畫時也能正常顯示 */
div[class*="analysis"]:not(.analysis-animation) {
  opacity: 1;
  transform: translateY(0);
}

/* 查證出處動畫 */
@keyframes sourcesSlideIn {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.sources-animation {
  animation: sourcesSlideIn 0.8s ease-out 1.8s forwards;
  opacity: 0;
  transform: translateY(30px);
}

/* 確保查證出處在沒有動畫時也能正常顯示 */
div[class*="sources"]:not(.sources-animation) {
  opacity: 1;
  transform: translateY(0);
}

/* 模態框動畫 */
.modal-enter-active, .modal-leave-active {
  transition: all 0.3s ease;
}
.modal-enter-from, .modal-leave-to {
  opacity: 0;
}
.modal-enter-from .bg-white {
  transform: scale(0.9) translateY(-20px);
}
.modal-leave-to .bg-white {
  transform: scale(0.9) translateY(-20px);
}
.modal-enter-to .bg-white, .modal-leave-from .bg-white {
  transform: scale(1) translateY(0);
}

.expand-enter-active,
.expand-leave-active {
  transition: height 0.3s ease;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  height: 0;
}
</style> 