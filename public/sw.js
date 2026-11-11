// Service worker van de web-app (PWA). Pagina's komen altijd van de server (de app werkt met live gegevens);
// zonder internet toont hij offline.html in plaats van de foutpagina van de browser.
// Ophogen als offline.html verandert, anders houden telefoons de oude versie
const CACHE = 'lukeboy-v2';
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE, '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
    }
});

// Pushmelding (rust voorbij, App\Support\WebPush). Staat de app open en in beeld, dan heeft die al gepiept:
// de melding wordt dan meteen weer gesloten (tonen moet wel, anders trekt iOS de toestemming in).
self.addEventListener('push', (event) => {
    const message = event.data?.json() ?? { title: 'Lukeboy Fitness', body: '' };

    event.waitUntil((async () => {
        await self.registration.showNotification(message.title, {
            body: message.body,
            icon: '/icons/icon-192.png',
            badge: '/icons/favicon-32.png',
            tag: message.tag ?? 'lukeboy',
            renotify: true,
            vibrate: [200, 100, 200],
            data: { url: message.url ?? '/' },
        });

        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        if (message.tag === 'rest' && windows.some((client) => client.visibilityState === 'visible')) {
            (await self.registration.getNotifications({ tag: 'rest' })).forEach((notification) => notification.close());
        }
    })());
});

// Tik op de melding: terug naar de training (bestaand venster als dat er is)
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url ?? '/', self.location.origin).href;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const open = windows.find((client) => client.url === url) ?? windows[0];
        if (open) {
            await open.focus();
            return open.url === url ? undefined : open.navigate(url);
        }
        return self.clients.openWindow(url);
    })());
});
