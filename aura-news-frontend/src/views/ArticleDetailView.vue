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

const credibilityData = ref(null);
const isAnalyzing = ref(false);
const analysisProgress = ref('');
const analysisError = ref(null);
let pollingInterval = null;

const credibilitySectionRef = ref(null);
const isCredibilitySectionVisible = ref(false);
const hasAnimationStarted = ref(false);

const popularitySectionRef = ref(null);
const isPopularitySectionVisible = ref(false);
const hasPopularityAnimationStarted = ref(false);

const imageLoading = ref(false);
const imageLoaded = ref(false);
const imageError = ref(false);

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

const credibilityScore = computed(() => {
  return credibilityData.value?.credibility_score || null;
});

const credibilityColor = computed(() => {
  if (credibilityScore.value === null) return '#d1d5db';
  if (credibilityScore.value >= 80) return '#22c55e';
  if (credibilityScore.value >= 60) return '#eab308';
  if (credibilityScore.value >= 40) return '#f97316';
  return '#ef4444';
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
    
    text = text.replace(
      /(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/g,
      (url) => {
        const cleanUrl = url.replace(/[),。！？；：\]\[\s]+$/g, '');
        const displayUrl = cleanUrl.length > 60 ? cleanUrl.substring(0, 60) + '...' : cleanUrl;
        return `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline break-all hover:whitespace-normal" title="${cleanUrl}">${displayUrl}</a>`;
      }
    );
    
    // 解析其他 Markdown 內容（除了連結）
    let html = marked.parse(text);
    
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

watch(credibilityData, (val) => {
  if (val && credibilitySectionRef.value && !hasAnimationStarted.value) {
    setupCredibilityAnimationObserver();
  }
});

watch(article, (val) => {
  if (val && val.popularity_score && popularitySectionRef.value && !hasPopularityAnimationStarted.value) {
    setupPopularityAnimationObserver();
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

const fetchCredibility = async () => {
  if (!article.value) return;
  
  try {
    const response = await axios.get(`https://api-news.vito1317.com/api/articles/${article.value.id}/credibility`);
    credibilityData.value = response.data;
  } catch (err) {
    if (err.response?.status === 404) {
      credibilityData.value = null;
    } else {
      console.error('可信度分析獲取失敗:', err);
    }
  }
};

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

const pollAnalysisProgress = (taskId) => {
  pollingInterval = setInterval(async () => {
    try {
      const response = await axios.get(`https://api-news.vito1317.com/api/articles/credibility/progress/${taskId}`);
      
      if (response.data.progress === '完成' && response.data.result) {
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
        return;
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
    
    await fetchCredibility();
    
    await nextTick();
    
    if (credibilitySectionRef.value) {
      setupCredibilityAnimationObserver();
    } else {
      setTimeout(() => {
        if (credibilitySectionRef.value) {
          setupCredibilityAnimationObserver();
        }
      }, 1000);
    }
    
    if (popularitySectionRef.value) {
      setupPopularityAnimationObserver();
    } else {
      setTimeout(() => {
        if (popularitySectionRef.value) {
          setupPopularityAnimationObserver();
        }
      }, 1000);
    }
  } catch (err) {
    console.error('文章獲取失敗:', err);
    error.value = '無法載入此篇文章，可能已被刪除或網址錯誤。';
  } finally {
    isLoading.value = false;
  }
  
  setTimeout(() => {
    if (credibilitySectionRef.value && !hasAnimationStarted.value) {
      setupCredibilityAnimationObserver();
    }
    if (popularitySectionRef.value && !hasPopularityAnimationStarted.value) {
      setupPopularityAnimationObserver();
    }
  }, 2000);
});

const setupCredibilityAnimationObserver = () => {
  if (!credibilitySectionRef.value) {
    return;
  }
  
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
      threshold: 0.1,
      rootMargin: '0px 0px -150px 0px'
    }
  );
  
  observer.observe(credibilitySectionRef.value);
  
  const handleScroll = () => {
    if (hasAnimationStarted.value) return;
    
    const rect = credibilitySectionRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    
    if (rect.top < windowHeight - 200 && rect.bottom > 0) {
      triggerAnimation();
      window.removeEventListener('scroll', handleScroll);
    }
  };
  
  window.addEventListener('scroll', handleScroll);
  
  setTimeout(() => {
    const rect = credibilitySectionRef.value.getBoundingClientRect();
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

const setupPopularityAnimationObserver = () => {
  if (!popularitySectionRef.value) {
    return;
  }
  
  const triggerAnimation = () => {
    if (hasPopularityAnimationStarted.value) return;
    
    hasPopularityAnimationStarted.value = true;
    
    setTimeout(() => {
      isPopularitySectionVisible.value = true;
    }, 300);
  };
  
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting && !hasPopularityAnimationStarted.value) {
          triggerAnimation();
        }
      });
    },
    {
      threshold: 0.1,
      rootMargin: '0px 0px -150px 0px'
    }
  );
  
  observer.observe(popularitySectionRef.value);
  
  const handleScroll = () => {
    if (hasPopularityAnimationStarted.value) return;
    
    const rect = popularitySectionRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    
    if (rect.top < windowHeight - 200 && rect.bottom > 0) {
      triggerAnimation();
      window.removeEventListener('scroll', handleScroll);
    }
  };
  
  window.addEventListener('scroll', handleScroll);
  
  setTimeout(() => {
    const rect = popularitySectionRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    
    if (rect.top < windowHeight - 200 && rect.bottom > 0 && !hasPopularityAnimationStarted.value) {
      triggerAnimation();
    }
  }, 500);
  
  onUnmounted(() => {
    observer.disconnect();
    window.removeEventListener('scroll', handleScroll);
  });
};

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

const handleImageError = (event) => {
  console.warn('圖片載入失敗:', event.target.src);
  imageLoading.value = false;
  imageLoaded.value = false;
  imageError.value = true;
};

const handleImageLoad = (event) => {
  imageLoading.value = false;
  imageLoaded.value = true;
  imageError.value = false;
};

const getPopularityLevel = (score) => {
  if (score >= 80) return '極高熱門度';
  if (score >= 60) return '高熱門度';
  if (score >= 40) return '中等熱門度';
  if (score >= 20) return '低熱門度';
  return '極低熱門度';
};


</script>

<template>
  <div class="py-6 sm:py-8 md:py-12 lg:py-16 xl:py-24">
    <div class="max-w-3xl mx-auto px-3 sm:px-4 lg:px-8">
      <div v-if="isLoading" class="text-center py-12 sm:py-16 lg:py-20">
        <div class="animate-spin w-6 h-6 sm:w-8 sm:h-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-3 sm:mb-4"></div>
        <p class="text-sm sm:text-base text-gray-500">載入中...</p>
      </div>
      <div v-else-if="error" class="bg-red-100 text-red-700 p-3 sm:p-4 rounded text-sm sm:text-base">{{ error }}</div>
      <article v-else-if="article">
        <div class="mb-3 sm:mb-4">
          <span class="inline-block bg-brand-light text-brand-dark font-semibold px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm">
            {{ article.category?.name || '新聞' }}
          </span>
        </div>
        <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 leading-tight mb-3 sm:mb-4">
          {{ article.title }}
        </h1>
        <div class="text-xs sm:text-sm text-gray-500 mb-6 sm:mb-8">
          <!-- 桌面版：水平排列 -->
          <div class="hidden sm:flex sm:items-center sm:gap-4">
            <span>作者: {{ article.author || '匿名' }}</span>
            <span>|</span>
            <span>{{ formatDate(article.published_at) }}</span>
            <span>|</span>
            <span class="flex items-center">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              {{ article.view_count || 0 }} 次觀看
            </span>
            <span v-if="article.popularity_score" class="flex items-center">
              <span>|</span>
              <svg class="w-4 h-4 mr-1 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              <span class="text-orange-600 font-medium">{{ Math.round(article.popularity_score) }}</span>
            </span>
          </div>
          
          <!-- 手機版：垂直排列，無間距 -->
          <div class="sm:hidden space-y-1">
            <div class="break-words">
              <span>作者: {{ article.author || '匿名' }}</span>
            </div>
            <div>{{ formatDate(article.published_at) }}</div>
            <div class="flex items-center">
              <svg class="w-3 h-3 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <span>{{ article.view_count || 0 }} 次觀看</span>
            </div>
            <div v-if="article.popularity_score" class="flex items-center">
              <svg class="w-3 h-3 mr-1 text-orange-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              <span class="text-orange-600 font-medium">{{ Math.round(article.popularity_score) }}</span>
            </div>
          </div>
        </div>
        <div class="mb-6 sm:mb-8 rounded-lg overflow-hidden shadow-lg relative w-full article-image-container">
          <div v-if="!article.image_url" class="absolute inset-0 bg-gray-200 flex items-center justify-center">
            <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <img
            v-if="article.image_url && !imageError"
            :src="article.image_url"
            :alt="article.title"
            @error="handleImageError"
            @load="handleImageLoad"
            @loadstart="imageLoading = true"
            class="w-full h-auto object-cover transition-opacity duration-300"
            :style="{ opacity: imageLoaded ? '1' : '0', width: '100%', height: 'auto', display: 'block' }"
            loading="lazy"
          />
          
          <img
            v-if="!article.image_url || imageError"
            :src="noImage"
            :alt="article.title"
            class="w-full h-auto object-cover"
            style="width: 100%; height: auto; display: block;"
          />
        </div>
        <div v-if="article.summary" class="mb-6 sm:mb-8 p-4 sm:p-6 bg-gray-50 rounded-lg border-l-4 border-brand-DEFAULT">
          <div class="text-base sm:text-lg italic text-gray-700 leading-relaxed" v-html="safeSummary"></div>
          <div class="text-xs text-gray-400 mt-2 flex items-center">
            <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 4V2m0 20v-2m7-7h-2M4 15H2m2.93-7.07l-1.42-1.42m12.02 12.02l1.42 1.42M19.07 4.93l-1.42 1.42M6.34 17.66l-1.42 1.42M8 12l4 4m0 0l4-4m-4 4V8"/></svg>
            （AI 摘要）
          </div>
        </div>
        <div 
          class="prose prose-lg max-w-none prose-h2:font-bold prose-h2:text-gray-800" 
          v-html="safeContent">
        </div>

        <div 
          v-if="article.popularity_score" 
          ref="popularitySectionRef"
          class="mt-8 sm:mt-12 p-4 sm:p-6 bg-gradient-to-br from-orange-50 to-yellow-50 rounded-xl border border-orange-200 popularity-section"
        >
          <div class="flex items-center justify-between mb-3 sm:mb-4">
            <h3 class="text-lg sm:text-xl font-bold text-orange-800 flex items-center">
              <svg class="w-5 h-5 sm:w-6 sm:h-6 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              熱門度評分
            </h3>
          </div>

          <div class="flex flex-col items-center">
            <div 
              class="relative sm:w-[120px] sm:h-[80px] sm:mx-auto" 
              :class="{ 'popularity-icon': isPopularitySectionVisible }" 
              style="width: 100px; height: 70px;"
            >
              <svg width="100" height="70" viewBox="0 0 120 80" class="sm:w-[105px] sm:h-[80px]">
                <path d="M 10 60 A 50 50 0 0 1 110 60" stroke="#f3f4f6" stroke-width="8" fill="none" stroke-linecap="round"/>
                <path 
                  d="M 10 60 A 50 50 0 0 1 110 60" 
                  stroke="#f97316" 
                  stroke-width="8" 
                  fill="none" 
                  stroke-linecap="round"
                  :stroke-dasharray="Math.PI * 50"
                  :stroke-dashoffset="isPopularitySectionVisible ? Math.PI * 50 * (1 - article.popularity_score / 100) : Math.PI * 50"
                  :style="{ '--final-offset': Math.PI * 50 * (1 - article.popularity_score / 100) + 'px' }"
                  :class="{ 'popularity-progress-animation': isPopularitySectionVisible }"
                />
                <path 
                  d="M 55 45 L 60 50 L 65 45" 
                  stroke="#f97316" 
                  stroke-width="3" 
                  fill="none" 
                  stroke-linecap="round" 
                  stroke-linejoin="round"
                  :class="{ 'popularity-check-animation': isPopularitySectionVisible }"
                />
                <text 
                  x="60" y="70" 
                  text-anchor="middle" 
                  font-size="16" 
                  font-weight="bold" 
                  fill="#f97316" 
                  dominant-baseline="middle"
                  :class="{ 'popularity-score-animation': isPopularitySectionVisible }"
                >
                  {{ Math.round(article.popularity_score) }}%
                </text>
              </svg>
            </div>
            <div class="mt-3">
              <div 
                class="px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium bg-orange-100 text-orange-800 transition-all duration-500 text-center"
                :class="{ 'popularity-level-animation': isPopularitySectionVisible }"
                style="text-align: center; min-width: 80px;"
              >
                {{ getPopularityLevel(article.popularity_score) }}
              </div>
            </div>
          </div>

          <div 
            class="mt-3 sm:mt-4 text-xs sm:text-sm text-gray-600 transition-all duration-700"
            :class="{ 'popularity-details-animation': isPopularitySectionVisible }"
          >
            <div class="grid grid-cols-2 gap-2 sm:gap-4 text-center">
              <div>
                <div class="font-medium">可信度</div>
                <div class="text-orange-600">{{ article.credibility_score || 0 }}%</div>
              </div>
              <div>
                <div class="font-medium">觀看次數</div>
                <div class="text-orange-600">{{ article.view_count || 0 }}</div>
              </div>
            </div>
          </div>
        </div>

        <div 
          ref="credibilitySectionRef"
          class="mt-8 sm:mt-12 p-4 sm:p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200"
        >

          <div class="flex items-center justify-between mb-3 sm:mb-4">
            <h3 class="text-lg sm:text-xl font-bold text-blue-800 flex items-center">
              <svg class="w-5 h-5 sm:w-6 sm:h-6 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" fill="none"/>
                <path d="M 7 12 L 10 15 L 17 8" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              AI 可信度分析
            </h3>
            <div class="flex space-x-2">
              <button 
                v-if="!credibilityData?.has_analysis && !isAnalyzing"
                @click="triggerCredibilityAnalysis"
                class="bg-blue-600 hover:bg-blue-700 text-white px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg transition-colors text-xs sm:text-sm font-medium"
              >
                開始分析
              </button>
            </div>
          </div>

          <div v-if="isAnalyzing" class="text-center py-6 sm:py-8">
            <div class="animate-spin w-6 h-6 sm:w-8 sm:h-8 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-3 sm:mb-4"></div>
            <p class="text-blue-700 font-medium text-sm sm:text-base">{{ analysisProgress || '正在分析中...' }}</p>
            <p class="text-xs sm:text-sm text-gray-500 mt-2">AI 正在查證新聞內容，請稍候</p>
          </div>

          <div v-else-if="analysisError" class="bg-red-100 border border-red-400 text-red-700 px-3 sm:px-4 py-2 sm:py-3 rounded text-sm sm:text-base">
            <p class="font-medium">{{ analysisError }}</p>
          </div>

          <div v-else-if="credibilityData?.has_analysis" class="space-y-3 sm:space-y-4">
            <div class="flex flex-col items-center">
              <div 
                class="relative sm:w-[120px] sm:h-[80px] sm:mx-auto" 
                :class="{ 'credibility-icon': isCredibilitySectionVisible }" 
                style="width: 100px; height: 70px;"
              >
                <svg width="100" height="70" viewBox="0 0 120 80" class="sm:w-[105px] sm:h-[80px]">
                  <path d="M 10 60 A 50 50 0 0 1 110 60" stroke="#f3f4f6" stroke-width="8" fill="none" stroke-linecap="round"/>
                  
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
                  
                  <path 
                    d="M 55 45 L 60 50 L 65 45" 
                    :stroke="credibilityColor" 
                    stroke-width="3" 
                    fill="none" 
                    stroke-linecap="round" 
                    stroke-linejoin="round"
                    :class="{ 'check-animation': isCredibilitySectionVisible }"
                  />
                  
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
              <div class="mt-3">
                <div 
                  class="px-3 py-1 rounded-full text-sm font-medium transition-all duration-500 text-center" 
                  :class="{ 'level-animation': isCredibilitySectionVisible }"
                  :style="{ backgroundColor: credibilityColor + '20', color: credibilityColor, textAlign: 'center', minWidth: '80px' }"
                >
                  {{ credibilityLevel }}
                </div>
              </div>
            </div>

            <div class="text-center text-xs sm:text-sm text-gray-500">
              分析時間: {{ credibilityData.credibility_checked_at ? new Date(credibilityData.credibility_checked_at).toLocaleString('zh-TW') : '' }}
            </div>

            <div 
              v-if="safeCredibilityAnalysis" 
              class="mt-4 sm:mt-6 p-3 sm:p-4 bg-white rounded-lg border transition-all duration-700"
              :class="{ 'analysis-animation': isCredibilitySectionVisible }"
            >
              <h4 class="font-semibold text-gray-800 mb-2 sm:mb-3 text-sm sm:text-base">詳細分析</h4>
              <div class="prose prose-xs sm:prose-sm max-w-none text-gray-700 break-words overflow-hidden" v-html="safeCredibilityAnalysis"></div>
            </div>
          </div>

          <div v-else class="text-center py-6 sm:py-8">
            <div class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-3 sm:mb-4">
              <svg width="48" height="48" viewBox="0 0 64 64" class="text-gray-400 sm:w-16 sm:h-16">
                <path d="M 10 42 A 22 22 0 0 1 54 42" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" opacity="0.6"/>
                
                <path d="M 26 30 Q 26 26 32 26 Q 38 26 38 30 Q 38 34 32 38 L 32 42" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="32" cy="46" r="1.5" fill="currentColor"/>
              </svg>
            </div>
            <p class="text-gray-600 mb-3 sm:mb-4 text-sm sm:text-base">此文章尚未進行可信度分析</p>
            <p class="text-xs sm:text-sm text-gray-500">點擊「開始分析」按鈕，AI 將自動查證新聞內容並給出可信度評分</p>
          </div>
        </div>

        <div class="mt-8 sm:mt-12 py-4 sm:py-6 border-t border-gray-200">
          <div class="flex flex-col sm:flex-row sm:items-center flex-wrap gap-x-4 gap-y-2">
            <span class="text-gray-600 font-semibold mb-2 sm:mb-0 text-sm sm:text-base">分享文章：</span>
            <div class="flex flex-wrap gap-2 sm:gap-4 gap-y-2">
              <button v-if="canWebShare" class="bg-blue-500 hover:bg-blue-600 text-white px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg transition-colors text-xs sm:text-sm" @click="webShare">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline w-4 h-4 sm:w-5 sm:h-5 mr-1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                </svg>
                分享
              </button>
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg transition-colors text-xs sm:text-sm" @click="() => share('facebook')">Facebook</button>
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg transition-colors text-xs sm:text-sm" @click="() => share('twitter')">Twitter</button>
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg transition-colors text-xs sm:text-sm" @click="() => share('line')">Line</button>
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

.prose a {
  word-break: break-all;
  overflow-wrap: break-word;
  hyphens: auto;
}

.prose {
  overflow-wrap: break-word;
  word-wrap: break-word;
  word-break: break-word;
}

.prose strong {
  display: block;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #1f2937;
}

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

.prose p:last-child {
  margin-bottom: 0;
}

.prose {
  max-width: 100%;
  overflow-wrap: break-word;
  word-wrap: break-word;
  word-break: break-word;
}

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
  transform-origin: center center;
}

@keyframes progressFill {
  from {
    stroke-dashoffset: 157;
  }
  to {
    stroke-dashoffset: var(--final-offset, 0);
  }
}

.progress-animation {
  animation: progressFill 1.5s ease-out forwards;
}

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

path[class*="check"]:not(.check-animation) {
  stroke-dasharray: none;
  stroke-dashoffset: 0;
}

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

text[class*="score"]:not(.score-animation) {
  opacity: 1;
  transform: scale(1);
}

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

span[class*="level"]:not(.level-animation) {
  opacity: 1;
  transform: translateY(0);
}

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

.article-image-container {
  width: 100%;
  max-width: 100%;
  overflow: hidden;
}

.article-image-container img {
  width: 100% !important;
  height: auto !important;
  display: block !important;
  object-fit: cover;
  object-position: center;
}

/* 熱門度區域動畫 */
.popularity-section {
  animation: slideInFromRight 0.8s ease-out;
}

@keyframes slideInFromRight {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* 熱門度圖標脈衝動畫 */
@keyframes popularityPulse {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.05);
    opacity: 0.8;
  }
}

.popularity-icon {
  animation: popularityPulse 2s ease-in-out infinite;
  transform-origin: center center;
}

/* 熱門度進度條動畫 */
@keyframes popularityProgressFill {
  from {
    stroke-dashoffset: 157;
  }
  to {
    stroke-dashoffset: var(--final-offset, 0);
  }
}

.popularity-progress-animation {
  animation: popularityProgressFill 1.5s ease-out forwards;
}

path[class*="popularity-progress"]:not(.popularity-progress-animation) {
  stroke-dashoffset: 0;
}

/* 熱門度勾選動畫 */
.popularity-check-animation {
  stroke-dasharray: 0 20;
  stroke-dashoffset: 20;
}

@keyframes popularityCheckDraw {
  from {
    stroke-dasharray: 0 20;
    stroke-dashoffset: 20;
  }
  to {
    stroke-dasharray: 20 0;
    stroke-dashoffset: 0;
  }
}

.popularity-check-animation {
  animation: popularityCheckDraw 0.8s ease-out 0.5s forwards;
  stroke-dasharray: 0 20;
  stroke-dashoffset: 20;
}

path[class*="popularity-check"]:not(.popularity-check-animation) {
  stroke-dasharray: none;
  stroke-dashoffset: 0;
}

/* 熱門度分數動畫 */
@keyframes popularityScoreFadeIn {
  from {
    opacity: 0;
    transform: scale(0.8);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.popularity-score-animation {
  animation: popularityScoreFadeIn 0.6s ease-out 1s forwards;
  opacity: 0;
  transform: scale(0.8);
}

text[class*="popularity-score"]:not(.popularity-score-animation) {
  opacity: 1;
  transform: scale(1);
}

/* 熱門度等級動畫 */
@keyframes popularityLevelSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.popularity-level-animation {
  animation: popularityLevelSlideIn 0.6s ease-out 1.2s forwards;
  opacity: 0;
  transform: translateY(20px);
}

span[class*="popularity-level"]:not(.popularity-level-animation) {
  opacity: 1;
  transform: translateY(0);
}

/* 熱門度詳細資訊動畫 */
@keyframes popularityDetailsFadeIn {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.popularity-details-animation {
  animation: popularityDetailsFadeIn 0.8s ease-out 1.5s forwards;
  opacity: 0;
  transform: translateY(30px);
}


</style> 