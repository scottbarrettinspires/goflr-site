// FLR Back Office app shell. Pages are always loaded fresh from the server (client data is never cached on the phone).
self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(function () {
    return new Response('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;background:#036f72;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;padding:20px"><div><h2>You are offline</h2><p>Connect to the internet and try again.</p><button onclick="location.reload()" style="padding:12px 20px;border:0;border-radius:10px;font-weight:700">Try again</button></div>', {headers: {'Content-Type': 'text/html'}});
  }));
});

// New-lead alerts: the push itself is empty; fetch the alert text for this phone, then show it.
self.addEventListener('push', function (e) {
  e.waitUntil(self.registration.pushManager.getSubscription().then(function (sub) {
    if (!sub) return null;
    return crypto.subtle.digest('SHA-256', new TextEncoder().encode(sub.endpoint)).then(function (buf) {
      var h = Array.prototype.map.call(new Uint8Array(buf), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
      return fetch('/push_latest.php?h=' + h, {cache: 'no-store'}).then(function (r) { return r.json(); });
    });
  }).catch(function () { return null; }).then(function (j) {
    var a = j && j.alert;
    return self.registration.showNotification(a ? a.title : 'New lead in FLR Office', {
      body: a ? a.body : 'Open the app to see it.', icon: '/icon-192.png', badge: '/icon-192.png',
      tag: a ? a.id : 'flr-lead', data: {url: a ? a.url : '/home.php'}, requireInteraction: true
    });
  }));
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || '/home.php';
  e.waitUntil(self.clients.matchAll({type: 'window', includeUncontrolled: true}).then(function (list) {
    for (var i = 0; i < list.length; i++) { if ('navigate' in list[i]) { return list[i].navigate(url).then(function (c) { return c && c.focus(); }); } }
    return self.clients.openWindow(url);
  }));
});
