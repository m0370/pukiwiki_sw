// Service Worker for PukiWiki (Default)
// Version: 1.0.0
// Author: @m0370
// License: GPL v2 or (at your option) any later version

const CACHE_NAME = 'pukiwiki-v1';
const CACHE_VERSION = '20251026-v1';

// キャッシュする静的リソース
const STATIC_CACHE_URLS = [
  // CSS
  '/skin/pukiwiki.css',

  // JavaScript
  '/skin/main.js',

  // PukiWikiロゴ
  '/image/pukiwiki.png',
  '/image/pukiwiki.gif',

  // よく使われるアイコン（編集・操作系）
  '/image/add.png',
  '/image/edit.png',
  '/image/copy.png',
  '/image/diff.png',
  '/image/reload.png',
  '/image/backup.png',
  '/image/freeze.png',
  '/image/unfreeze.png',
  '/image/rename.png',

  // ナビゲーション系アイコン
  '/image/search.png',
  '/image/list.png',
  '/image/help.png',
  '/image/new.png',
  '/image/top.png',
  '/image/recentchanges.png',

  // フィード系
  '/image/rss.png',
  '/image/rss20.png',
  '/image/atom.png',

  // その他のよく使われるアイコン
  '/image/external_link.gif',
  '/image/file.png',
  '/image/paraedit.png'
];

// インストール時: 静的リソースをキャッシュ
self.addEventListener('install', event => {
  console.log('[SW] Install');
  event.waitUntil(
    caches.open(CACHE_NAME + '-' + CACHE_VERSION)
      .then(cache => {
        console.log('[SW] Caching static resources');
        return cache.addAll(STATIC_CACHE_URLS);
      })
      .then(() => self.skipWaiting())
  );
});

// アクティベーション時: 古いキャッシュを削除
self.addEventListener('activate', event => {
  console.log('[SW] Activate');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames
          .filter(cacheName => cacheName.startsWith('pukiwiki-default-'))
          .filter(cacheName => cacheName !== CACHE_NAME + '-' + CACHE_VERSION)
          .map(cacheName => {
            console.log('[SW] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          })
      );
    }).then(() => self.clients.claim())
  );
});

// フェッチ時: Cache-First戦略（静的リソース）、Network-First戦略（HTML）
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // 同一オリジンのみ処理
  if (url.origin !== location.origin) {
    return;
  }

  // GETリクエストのみ処理
  if (event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      // 静的リソース（CSS/JS/画像）: Cache-First
      if (event.request.url.match(/\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2)$/)) {
        return cachedResponse || fetch(event.request).then(response => {
          // 成功時のみキャッシュ
          if (response && response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME + '-' + CACHE_VERSION).then(cache => {
              cache.put(event.request, responseToCache);
            });
          }
          return response;
        });
      }

      // HTML: Network-First（常に最新を取得、失敗時のみキャッシュ）
      return fetch(event.request)
        .then(response => {
          if (response && response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME + '-' + CACHE_VERSION).then(cache => {
              cache.put(event.request, responseToCache);
            });
          }
          return response;
        })
        .catch(() => cachedResponse);
    })
  );
});