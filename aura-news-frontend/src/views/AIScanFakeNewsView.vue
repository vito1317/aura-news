<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { marked } from 'marked';
import { useHead } from '@vueuse/head';

// 自訂 link renderer，避免連結後方夾帶非網址字元
const renderer = {
  link(href, title, text) {
    // 強制 href 與 text 皆為 string，避免 TypeError
    href = typeof href === 'string' ? href : '';
    text = typeof text === 'string' ? text : '';
    // 僅保留連續網址部分，去除常見結尾雜訊
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

// 動畫相關狀態
const resultSectionRef = ref(null);
const isResultSectionVisible = ref(false);
const hasAnimationStarted = ref(false);

const steps = computed(() => {
  if (/^https?:\/\//i.test(input.value.trim())) {
    return [
      '分析網址',
      'AI 產生搜尋關鍵字',
      '新聞資料搜尋',
      'AI 綜合查證',
      '完成',
    ];
  } else {
    return [
      '分析內容',
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
  if (p.includes('AI 綜合查證')) return 3;
  if (p.includes('新聞資料') || p.includes('站內搜尋')) return 2;
  if (p.includes('AI 產生搜尋關鍵字')) return 1;
  if (p.includes('抓取主文') || p.includes('查看新聞') || p.includes('分析網址') || p.includes('分析內容')) return 0;
  return 0;
});

const confidence = computed(() => {
  if (!result.value?.result) return null;
  const match = result.value.result.match(/【可信度：(\d+)%?】/);
  return match ? parseInt(match[1], 10) : null;
});
const confidenceColor = computed(() => {
  if (confidence.value === null) return '#d1d5db'; // gray-300
  if (confidence.value >= 80) return '#22c55e'; // green-500
  if (confidence.value >= 60) return '#eab308'; // yellow-500
  if (confidence.value >= 40) return '#f97316'; // orange-500
  return '#ef4444'; // red-500
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

// 新增函式：將文字中的網址自動轉為 <a> 連結
function linkify(text) {
  if (!text) return '';
  // 強制轉為 string，避免 TypeError
  text = String(text);
  // 更嚴謹的網址偵測，避免混到非網址字元
  return text.replace(/(https?:\/\/[\w\-\.\/?#=&%+:;@,~]+)(?=[\s\n\r\)\]\}。，！？；：]|$)/g, (url) => {
    // 僅保留連續網址部分，去除尾端雜訊
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
  
  // 重置動畫狀態
  isResultSectionVisible.value = false;
  hasAnimationStarted.value = false;
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
  try {
    const res = await axios.post('/api/ai/scan-fake-news/start', { content: input.value });
    // 新增：若後端回傳 error 欄位，直接顯示錯誤
    if (res.data && res.data.error) {
      error.value = res.data.error;
      isLoading.value = false;
      return;
    }
    const taskId = res.data.taskId;
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
      // 新增：偵測非新聞內容
      if (res.data.error === '此內容非新聞，請確認輸入') {
        error.value = res.data.error;
        isLoading.value = false;
        clearInterval(pollingInterval);
        pollingInterval = null;
        return;
      }
      // 自動結束流程：主文擷取失敗或請求過於頻繁
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
        result.value = { result: res.data.result };
        isLoading.value = false;
        clearInterval(pollingInterval);
        pollingInterval = null;
        
        // 等待 DOM 更新後設置動畫
        nextTick(() => {
          setupAnimationWhenResultAvailable();
        });
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

// 設置結果動畫觀察器
const setupResultAnimationObserver = () => {
  if (!resultSectionRef.value) {
    return;
  }
  
  // 簡化的觸發動畫函數
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
      threshold: 0.1, // 當 10% 的區塊可見時觸發
      rootMargin: '0px 0px -150px 0px' // 提前 150px 觸發
    }
  );
  
  observer.observe(resultSectionRef.value);
  
  // 滾動監聽器
  const handleScroll = () => {
    if (hasAnimationStarted.value) return;
    
    const rect = resultSectionRef.value.getBoundingClientRect();
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
    const rect = resultSectionRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    
    if (rect.top < windowHeight - 200 && rect.bottom > 0 && !hasAnimationStarted.value) {
      triggerAnimation();
    }
  }, 500);
  
  // 在組件卸載時清理
  onUnmounted(() => {
    observer.disconnect();
    window.removeEventListener('scroll', handleScroll);
  });
};

// 監聽結果變化，設置動畫
const setupAnimationWhenResultAvailable = () => {
  if (result.value && resultSectionRef.value && !hasAnimationStarted.value) {
    setupResultAnimationObserver();
  }
};

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
</script>

<template>
  <div class="max-w-2xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
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
          <template v-if="error === '此內容非新聞，請確認輸入'">
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

    <!-- 進度條/步驟條 -->
    <div v-if="isLoading || (progress && progress !== '完成')" class="mb-8">
      <div class="flex items-center justify-between mb-2">
        <span class="text-blue-700 font-semibold">AI 掃描進度</span>
        <span class="text-sm text-gray-500">{{ progress }}</span>
      </div>
      <div class="flex items-center justify-between">
        <template v-for="(step, idx) in steps" :key="step">
          <div class="flex flex-col items-center flex-1">
            <div :class="[
              'w-8 h-8 rounded-full flex items-center justify-center font-bold mb-1',
              idx < currentStep ? 'bg-blue-500 text-white' : idx === currentStep ? 'bg-blue-200 text-blue-800 border-2 border-blue-500' : 'bg-gray-200 text-gray-400'
            ]">
              {{ idx + 1 }}
            </div>
            <span :class="['text-xs', idx <= currentStep ? 'text-blue-700 font-semibold' : 'text-gray-400']">{{ step }}</span>
          </div>
          <div v-if="idx < steps.length - 1" class="flex-1 h-1 bg-gradient-to-r from-blue-200 to-blue-400 mx-1"></div>
        </template>
      </div>
    </div>

    <!-- 結果區塊 -->
    <transition name="fade">
      <div 
        v-if="result" 
        ref="resultSectionRef"
        class="mt-8 p-8 bg-gradient-to-br from-blue-50 to-white rounded-2xl border border-blue-100 shadow"
        :class="{ 'result-animation': isResultSectionVisible }"
      >
        <div class="flex items-center mb-4">
          <svg class="h-8 w-8 text-green-500 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2l4-4m5 2a9 9 0 11-18 0a9 9 0 0118 0z"/>
          </svg>
          <h2 class="text-xl font-bold text-blue-800">AI 判斷結果</h2>
        </div>
        <div v-if="confidence !== null" class="mb-4 flex flex-col items-center justify-center" style="height:200px; margin-bottom: 0;">
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
</style> 