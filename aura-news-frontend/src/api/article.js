const API_BASE = import.meta.env.VITE_API_BASE || 'https://api-news.vito1317.com';

export async function markArticleAsRead(articleId) {
  await fetch(`${API_BASE}/api/articles/${articleId}/read`, { method: 'POST', credentials: 'include' });
}

export async function fetchRecommendedArticles() {
  const res = await fetch(`${API_BASE}/api/articles/recommend`, { credentials: 'include' });
  return res.json();
} 