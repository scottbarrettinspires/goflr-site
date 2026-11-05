// FLR Back Office app shell. Pages are always loaded fresh from the server (client data is never cached on the phone).
self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(function () {
    return new Response('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;background:#036f72;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;padding:20px"><div><h2>You are offline</h2><p>Connect to the internet and try again.</p><button onclick="location.reload()" style="padding:12px 20px;border:0;border-radius:10px;font-weight:700">Try again</button></div>', {headers: {'Content-Type': 'text/html'}});
  }));
});
