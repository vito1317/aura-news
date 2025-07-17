const { SitemapStream, streamToPromise } = require('sitemap');
const { createWriteStream } = require('fs');
const fetch = require('node-fetch');

(async () => {
  const baseUrl = 'https://news.vito1317.com';

  const articles = await fetch('https://api-news.vito1317.com/api/articles')
    .then(res => res.json())
    .then(data => Array.isArray(data.data) ? data.data : data);

  const categories = await fetch('https://api-news.vito1317.com/api/categories')
    .then(res => res.json())
    .then(data => Array.isArray(data.data) ? data.data : data);

  const links = [
    { url: '/', changefreq: 'daily', priority: 1.0 },
    ...articles.map(article => ({
      url: `/article/${article.id}`,
      changefreq: 'weekly',
      priority: 0.8,
    })),
    ...categories.map(category => ({
      url: `/category/${category.slug}`,
      changefreq: 'weekly',
      priority: 0.7,
    })),
  ];

  const sitemap = new SitemapStream({ hostname: baseUrl });
  const writeStream = createWriteStream('./public/sitemap.xml');
  sitemap.pipe(writeStream);

  links.forEach(link => sitemap.write(link));
  sitemap.end();

  await streamToPromise(sitemap);
  console.log('Sitemap generated!');
})(); 