<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import noImage from '@/assets/no-image.jpg';
import { useHead } from '@vueuse/head';

const route = useRoute();
const article = ref(null);
const isLoading = ref(true);
const error = ref(null);

// 可信度相關狀態
const credibilityData = ref(null);
const isAnalyzing = ref(false);
const analysisProgress = ref('');
const analysisError = ref(null);
let pollingInterval = null;

// 動畫相關狀態
const credibilitySectionRef = ref(null);
const isCredibilitySectionVisible = ref(false);
const hasAnimationStarted = ref(false);

const safeSummary = computed(() => {
  if (article.value && article.value.summary) {
    const html = marked.parse(article.value.summary);
    return DOMPurify.sanitize(html);
  }
  return '';
});
const safeContent = computed(() => {
  if (article.value && article.value.content) {
    const html = marked.parse(article.value.content);
    return DOMPurify.sanitize(html);
  }
  return '';
});

// 可信度相關計算屬性
const credibilityScore = computed(() => {
  return credibilityData.value?.credibility_score || null;
});

const credibilityColor = computed(() => {
  if (credibilityScore.value === null) return '#d1d5db'; // gray-300
  if (credibilityScore.value >= 80) return '#22c55e'; // green-500
  if (credibilityScore.value >= 60) return '#eab308'; // yellow-500
  if (credibilityScore.value >= 40) return '#f97316'; // orange-500
  return '#ef4444'; // red-500
});

const credibilityLevel = computed(() => {
  if (credibilityScore.value === null) return '未分析';
  if (credibilityScore.value >= 80) return '極高可信度';
  if (credibilityScore.value >= 60) return '高可信度';
  if (credibilityScore.value >= 40) return '中等可信度';
  if (credibilityScore.value >= 20) return '低可信度';
  return '極低可信度';
});

const safeCredibilityAnalysis = computed(() => {
  if (credibilityData.value?.credibility_analysis) {
    let text = credibilityData.value.credibility_analysis;
    
    // 先處理查證出處區塊
    const sourceMatch = text.match(/【查證出處】([\s\S]*)$/);
    if (sourceMatch) {
      const sourceContent = sourceMatch[1];
      const formattedSources = sourceContent.replace(
        /(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/g,
        (url) => {
          const cleanUrl = url.replace(/[),。！？；：\]\[\s]+$/g, '');
          const displayUrl = cleanUrl.length > 50 ? cleanUrl.substring(0, 50) + '...' : cleanUrl;
          return `[${displayUrl}](${cleanUrl})`;
        }
      );
      text = text.replace(sourceMatch[0], `【查證出處】${formattedSources}`);
    }
    
    // 處理其他 URL（排除已經處理過的 Markdown 連結）
    text = text.replace(
      /(?<!\[)(https?:\/\/[^\s<>"{}|\\^`\[\]]+)(?!\])/g,
      (url) => {
        const cleanUrl = url.replace(/[),。！？；：\]\[\s]+$/g, '');
        const displayUrl = cleanUrl.length > 60 ? cleanUrl.substring(0, 60) + '...' : cleanUrl;
        return `[${displayUrl}](${cleanUrl})`;
      }
    );
    
    // 轉換為 HTML
    let html = marked.parse(text);
    
    // 為所有連結添加額外的 CSS 類別
    html = html.replace(
      /<a href="([^"]+)">([^<]+)<\/a>/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline break-all hover:whitespace-normal" title="$1">$2</a>'
    );
    
    return DOMPurify.sanitize(html);
  }
  return '';
});

watch(article, (val) => {
  if (val) {
    useHead({
      title: `${val.title} - Aura News`,
      meta: [
        { name: 'description', content: val.summary || val.title },
        { property: 'og:title', content: val.title },
        { property: 'og:description', content: val.summary || val.title },
        { property: 'og:type', content: 'article' },
        { property: 'og:image', content: val.image_url || '/favicon.ico' },
        { property: 'og:url', content: typeof window !== 'undefined' ? window.location.href : '' },
        { name: 'twitter:card', content: 'summary_large_image' }
      ],
      script: [
        {
          type: 'application/ld+json',
          children: JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'NewsArticle',
            headline: val.title,
            datePublished: val.published_at,
            author: { '@type': 'Person', name: val.author || '匿名' },
            image: val.image_url || '',
            articleBody: val.content || ''
          })
        }
      ]
    });
  }
});

// 監聽可信度數據變化，確保在數據載入後設置 Observer
watch(credibilityData, (val) => {
  if (val && credibilitySectionRef.value && !hasAnimationStarted.value) {
    setupCredibilityAnimationObserver();
  }
});

const share = (platform) => {
  const url = encodeURIComponent(window.location.href);
  const title = encodeURIComponent(article.value?.title || document.title);
  let shareUrl = '';
  if (platform === 'facebook') {
    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
  } else if (platform === 'twitter') {
    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
  } else if (platform === 'line') {
    shareUrl = `https://social-plugins.line.me/lineit/share?url=${url}`;
  }
  if (shareUrl) {
    window.open(shareUrl, '_blank', 'noopener,width=600,height=600');
  }
};

const canWebShare = typeof navigator !== 'undefined' && !!navigator.share;

const webShare = async () => {
  if (!article.value) return;
  try {
    await navigator.share({
      title: article.value.title,
      text: article.value.summary || '',
      url: window.location.href
    });
  } catch (err) {
    console.warn('Web Share 失敗:', err);
  }
};

// 獲取可信度分析結果
const fetchCredibility = async () => {
  if (!article.value) return;
  
  try {
    const response = await axios.get(`https://api-news.vito1317.com/api/articles/${article.value.id}/credibility`);
    credibilityData.value = response.data;
  } catch (err) {
    if (err.response?.status === 404) {
      // 文章沒有可信度分析，這是正常的
      credibilityData.value = null;
    } else {
      console.error('可信度分析獲取失敗:', err);
    }
  }
};

// 觸發可信度分析
const triggerCredibilityAnalysis = async () => {
  if (!article.value || isAnalyzing.value) return;
  
  isAnalyzing.value = true;
  analysisError.value = null;
  analysisProgress.value = '';
  
  try {
    const response = await axios.post(`https://api-news.vito1317.com/api/articles/${article.value.id}/credibility/analyze`);
    
    if (response.data.taskId) {
      pollAnalysisProgress(response.data.taskId);
    } else {
      analysisError.value = response.data.message || '分析啟動失敗';
      isAnalyzing.value = false;
    }
  } catch (err) {
    analysisError.value = err.response?.data?.error || err.response?.data?.message || '分析啟動失敗';
    isAnalyzing.value = false;
  }
};

// 輪詢分析進度
const pollAnalysisProgress = (taskId) => {
  pollingInterval = setInterval(async () => {
    try {
      const response = await axios.get(`https://api-news.vito1317.com/api/articles/credibility/progress/${taskId}`);
      
      if (response.data.progress === '完成' && response.data.result) {
        // 分析完成，重新獲取可信度結果
        clearInterval(pollingInterval);
        pollingInterval = null;
        isAnalyzing.value = false;
        analysisProgress.value = '';
        await fetchCredibility();
      } else if (response.data.progress === 'not_found') {
        clearInterval(pollingInterval);
        pollingInterval = null;
        isAnalyzing.value = false;
        analysisError.value = '分析任務不存在';
      } else {
        analysisProgress.value = response.data.progress;
      }
    } catch (err) {
      if (err.response?.status === 404) {
        return; // 繼續輪詢
      }
      clearInterval(pollingInterval);
      pollingInterval = null;
      isAnalyzing.value = false;
      analysisError.value = '進度查詢失敗';
    }
  }, 2000);
};

onMounted(async () => {
  const articleId = route.params.id;
  try {
    const response = await axios.get(`https://api-news.vito1317.com/api/articles/${articleId}`);
    article.value = response.data;
    
    // 獲取可信度分析結果
    await fetchCredibility();
    
    // 等待 DOM 更新後設置 Intersection Observer
    await nextTick();
    
    // 確保可信度區塊已經渲染
    if (credibilitySectionRef.value) {
      setupCredibilityAnimationObserver();
    } else {
      // 如果還沒渲染，等待更長時間
      setTimeout(() => {
        if (credibilitySectionRef.value) {
          setupCredibilityAnimationObserver();
        }
      }, 1000);
    }
  } catch (err) {
    console.error('文章獲取失敗:', err);
    error.value = '無法載入此篇文章，可能已被刪除或網址錯誤。';
  } finally {
    isLoading.value = false;
  }
  
  // 額外的檢查：在組件完全掛載後再次檢查
  setTimeout(() => {
    if (credibilitySectionRef.value && !hasAnimationStarted.value) {
      setupCredibilityAnimationObserver();
    }
  }, 2000);
});

// 設置可信度動畫觀察器
const setupCredibilityAnimationObserver = () => {
  if (!credibilitySectionRef.value) {
    return;
  }
  
  // 簡化的觸發動畫函數
  const triggerAnimation = () => {
    if (hasAnimationStarted.value) return;
    
    hasAnimationStarted.value = true;
    
    setTimeout(() => {
      isCredibilitySectionVisible.value = true;
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
      threshold: 0.1, // 當 10% 的區塊可見時觸發
      rootMargin: '0px 0px -150px 0px' // 提前 150px 觸發
    }
  );
  
  observer.observe(credibilitySectionRef.value);
  
  // 滾動監聽器
  const handleScroll = () => {
    if (hasAnimationStarted.value) return;
    
    const rect = credibilitySectionRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    
    // 當區塊進入視窗底部 200px 範圍內時觸發
    if (rect.top < windowHeight - 200 && rect.bottom > 0) {
      triggerAnimation();
      window.removeEventListener('scroll', handleScroll);
    }
  };
  
  window.addEventListener('scroll', handleScroll);
  
  // 立即檢查一次
  setTimeout(() => {
    const rect = credibilitySectionRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    
    if (rect.top < windowHeight - 200 && rect.bottom > 0 && !hasAnimationStarted.value) {
      triggerAnimation();
    }
  }, 500);
  
  // 移除強制觸發動畫功能
  // setTimeout(() => {
  //   if (!hasAnimationStarted.value && credibilityData.value?.has_analysis) {
  //     console.log('2秒後強制觸發動畫');
  //     triggerAnimation();
  //   }
  // }, 2000);
  
  // 在組件卸載時清理
  onUnmounted(() => {
    observer.disconnect();
    window.removeEventListener('scroll', handleScroll);
  });
};

// 移除測試動畫函數

// 移除檢查可見性函數

// 移除重置動畫函數

// 組件卸載時清理輪詢
onUnmounted(() => {
  if (pollingInterval) {
    clearInterval(pollingInterval);
  }
});

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).replace(/\//g, '.'); 
}


</script>

<template>
  <div class="py-12 md:py-24">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div v-if="isLoading" class="text-center py-20 text-gray-500">載入中...</div>
      <div v-else-if="error" class="bg-red-100 text-red-700 p-4 rounded">{{ error }}</div>
      <article v-else-if="article">
        <div class="mb-4">
          <span class="inline-block bg-brand-light text-brand-dark font-semibold px-3 py-1 rounded-full text-sm">
            {{ article.category?.name || '新聞' }}
          </span>
        </div>
        <h1 class="text-3xl md:text-5xl font-bold text-gray-900 leading-tight mb-4">
          {{ article.title }}
        </h1>
        <div class="flex items-center space-x-4 text-sm text-gray-500 mb-8">
          <span>作者: {{ article.author || '匿名' }}</span>
          <span>|</span>
          <span>{{ formatDate(article.published_at) }}</span>
        </div>
        <div class="mb-8 rounded-lg overflow-hidden shadow-lg">
          <img
            :src="article.image_url || noImage"
            :alt="article.title"
            @error="e => e.target.src = noImage"
          />
        </div>
        <div v-if="article.summary" class="mb-8 p-6 bg-gray-50 rounded-lg border-l-4 border-brand-DEFAULT">
          <div class="text-lg italic text-gray-700 leading-relaxed" v-html="safeSummary"></div>
          <div class="text-xs text-gray-400 mt-2 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 4V2m0 20v-2m7-7h-2M4 15H2m2.93-7.07l-1.42-1.42m12.02 12.02l1.42 1.42M19.07 4.93l-1.42 1.42M6.34 17.66l-1.42 1.42M8 12l4 4m0 0l4-4m-4 4V8"/></svg>
            （AI 摘要）
          </div>
        </div>
        <div 
          class="prose prose-lg max-w-none prose-h2:font-bold prose-h2:text-gray-800" 
          v-html="safeContent">
        </div>

        <!-- 可信度分析區塊 -->
        <div 
          ref="credibilitySectionRef"
          class="mt-12 p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200"
        >

          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-blue-800 flex items-center">
              <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <!-- 圓形背景 -->
                <circle cx="12" cy="12" r="10" stroke="currentColor" fill="none"/>
                <!-- 打勾符號 -->
                <path d="M 7 12 L 10 15 L 17 8" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              AI 可信度分析
            </h3>
            <div class="flex space-x-2">
              <button 
                v-if="!credibilityData?.has_analysis && !isAnalyzing"
                @click="triggerCredibilityAnalysis"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium"
              >
                開始分析
              </button>
            </div>
          </div>

          <!-- 分析中狀態 -->
          <div v-if="isAnalyzing" class="text-center py-8">
            <div class="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-4"></div>
            <p class="text-blue-700 font-medium">{{ analysisProgress || '正在分析中...' }}</p>
            <p class="text-sm text-gray-500 mt-2">AI 正在查證新聞內容，請稍候</p>
          </div>

          <!-- 錯誤狀態 -->
          <div v-else-if="analysisError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <p class="font-medium">{{ analysisError }}</p>
          </div>

          <!-- 可信度結果 -->
          <div v-else-if="credibilityData?.has_analysis" class="space-y-4">
            <!-- 可信度分數半圓形圖標 -->
            <div class="flex justify-center">
              <div 
                class="relative" 
                :class="{ 'credibility-icon': isCredibilitySectionVisible }" 
                style="width: 120px; height: 80px;"
              >
                <svg width="120" height="80" viewBox="0 0 120 80">
                  <!-- 背景半圓 -->
                  <path d="M 10 60 A 50 50 0 0 1 110 60" stroke="#f3f4f6" stroke-width="8" fill="none" stroke-linecap="round"/>
                  
                  <!-- 進度半圓 -->
                  <path 
                    d="M 10 60 A 50 50 0 0 1 110 60" 
                    :stroke="credibilityColor" 
                    stroke-width="8" 
                    fill="none" 
                    stroke-linecap="round"
                    :stroke-dasharray="Math.PI * 50"
                    :stroke-dashoffset="isCredibilitySectionVisible ? Math.PI * 50 * (1 - credibilityScore / 100) : Math.PI * 50"
                    :style="{ '--final-offset': Math.PI * 50 * (1 - credibilityScore / 100) + 'px' }"
                    :class="{ 'progress-animation': isCredibilitySectionVisible }"
                  />
                  
                  <!-- 中心勾號 -->
                  <path 
                    d="M 55 45 L 60 50 L 65 45" 
                    :stroke="credibilityColor" 
                    stroke-width="3" 
                    fill="none" 
                    stroke-linecap="round" 
                    stroke-linejoin="round"
                    :class="{ 'check-animation': isCredibilitySectionVisible }"
                  />
                  
                  <!-- 分數文字 -->
                  <text 
                    x="60" y="70" 
                    text-anchor="middle" 
                    font-size="16" 
                    font-weight="bold" 
                    :fill="credibilityColor" 
                    dominant-baseline="middle"
                    :class="{ 'score-animation': isCredibilitySectionVisible }"
                  >
                    {{ credibilityScore }}%
                  </text>
                </svg>
              </div>
            </div>

            <!-- 可信度等級 -->
            <div class="text-center">
              <span 
                class="inline-block px-3 py-1 rounded-full text-sm font-medium transition-all duration-500" 
                :class="{ 'level-animation': isCredibilitySectionVisible }"
                :style="{ backgroundColor: credibilityColor + '20', color: credibilityColor }"
              >
                {{ credibilityLevel }}
              </span>
            </div>

            <!-- 分析時間 -->
            <div class="text-center text-sm text-gray-500">
              分析時間: {{ credibilityData.credibility_checked_at ? new Date(credibilityData.credibility_checked_at).toLocaleString('zh-TW') : '' }}
            </div>

            <!-- 詳細分析內容 -->
            <div 
              v-if="safeCredibilityAnalysis" 
              class="mt-6 p-4 bg-white rounded-lg border transition-all duration-700"
              :class="{ 'analysis-animation': isCredibilitySectionVisible }"
            >
              <h4 class="font-semibold text-gray-800 mb-3">詳細分析</h4>
              <div class="prose prose-sm max-w-none text-gray-700 break-words overflow-hidden" v-html="safeCredibilityAnalysis"></div>
            </div>
          </div>

          <!-- 未分析狀態 -->
          <div v-else class="text-center py-8">
            <div class="w-16 h-16 mx-auto mb-4">
              <svg width="64" height="64" viewBox="0 0 64 64" class="text-gray-400">
                <!-- 半圓形背景 -->
                <path d="M 8 40 A 24 24 0 0 1 56 40" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round"/>
                
                <!-- 問號圖標 -->
                <path d="M 28 20 Q 28 16 32 16 Q 36 16 36 20 Q 36 24 32 28 L 32 32" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="32" cy="36" r="1" fill="currentColor"/>
              </svg>
            </div>
            <p class="text-gray-600 mb-4">此文章尚未進行可信度分析</p>
            <p class="text-sm text-gray-500">點擊「開始分析」按鈕，AI 將自動查證新聞內容並給出可信度評分</p>
          </div>
        </div>

        <div class="mt-12 py-6 border-t border-gray-200">
          <div class="flex flex-col sm:flex-row sm:items-center flex-wrap gap-x-4 gap-y-2">
            <span class="text-gray-600 font-semibold mb-2 sm:mb-0">分享文章：</span>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
              <button v-if="canWebShare" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors" @click="webShare">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline w-5 h-5 mr-1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                </svg>
                分享
              </button>
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors" @click="() => share('facebook')">Facebook</button>
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors" @click="() => share('twitter')">Twitter</button>
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition-colors" @click="() => share('line')">Line</button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<style>
.prose p {
  margin-bottom: 1.25em;
}
.prose h2 {
  font-size: 1.5em;
  margin-top: 1.5em;
  margin-bottom: 1em;
}

/* 可信度分析內容的 URL 處理 */
.prose a {
  word-break: break-all;
  overflow-wrap: break-word;
  hyphens: auto;
}

/* 確保長 URL 不會超出容器 */
.prose {
  overflow-wrap: break-word;
  word-wrap: break-word;
  word-break: break-word;
}

/* 查證出處區塊的特殊樣式 */
.prose strong {
  display: block;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #1f2937;
}

/* 列表項的 URL 處理 */
.prose ul li a,
.prose ol li a {
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.prose ul li a:hover,
.prose ol li a:hover {
  white-space: normal;
  word-break: break-all;
  background-color: #f3f4f6;
  padding: 2px 4px;
  border-radius: 4px;
}

/* 查證出處區塊的特殊樣式 */
.prose p:last-child {
  margin-bottom: 0;
}

/* 確保長 URL 在容器內正確換行 */
.prose {
  max-width: 100%;
  overflow-wrap: break-word;
  word-wrap: break-word;
  word-break: break-word;
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

.credibility-icon {
  animation: credibilityPulse 2s ease-in-out infinite;
}

/* 半圓形進度條動畫 */
@keyframes progressFill {
  from {
    stroke-dashoffset: 157; /* Math.PI * 50 - 完全隱藏 */
  }
  to {
    stroke-dashoffset: var(--final-offset, 0); /* 使用 CSS 變數 */
  }
}

.progress-animation {
  animation: progressFill 1.5s ease-out forwards;
}

/* 確保進度條在沒有動畫時也能正常顯示 */
path[class*="progress"]:not(.progress-animation) {
  stroke-dashoffset: 0;
}

.check-animation {
  stroke-dasharray: 0 20;
  stroke-dashoffset: 20;
}

.score-animation {
  opacity: 0;
  transform: scale(0.8);
}

.level-animation {
  opacity: 0;
  transform: translateY(20px);
}

.analysis-animation {
  opacity: 0;
  transform: translateY(30px);
}

/* 勾號動畫 */
@keyframes checkDraw {
  from {
    stroke-dasharray: 0 20;
    stroke-dashoffset: 20;
  }
  to {
    stroke-dasharray: 20 0;
    stroke-dashoffset: 0;
  }
}

.check-animation {
  animation: checkDraw 0.8s ease-out 0.5s forwards;
  stroke-dasharray: 0 20;
  stroke-dashoffset: 20;
}

/* 確保勾號在沒有動畫時也能正常顯示 */
path[class*="check"]:not(.check-animation) {
  stroke-dasharray: none;
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

/* 確保分數文字在沒有動畫時也能正常顯示 */
text[class*="score"]:not(.score-animation) {
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

/* 詳細分析內容動畫 */
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

/* 確保詳細分析在沒有動畫時也能正常顯示 */
div[class*="analysis"]:not(.analysis-animation) {
  opacity: 1;
  transform: translateY(0);
}
</style> 