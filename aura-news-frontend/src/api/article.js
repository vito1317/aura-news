const API_BASE = import.meta.env.VITE_API_BASE || 'https://api-news.vito1317.com';

export async function markArticleAsRead(articleId) {
  const token = localStorage.getItem('token');
  await fetch(`${API_BASE}/api/articles/${articleId}/read`, {
    method: 'POST',
    credentials: 'include',
    headers: token ? { 'Authorization': `Bearer ${token}` } : {},
  });
}
 
export async function fetchRecommendedArticles() {
  const res = await fetch(`${API_BASE}/api/articles/recommend`, { credentials: 'include' });
  return res.json();
} 