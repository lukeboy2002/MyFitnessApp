// Service worker van de web-app (PWA). Pagina's komen altijd van de server (de app werkt met live gegevens);
// zonder internet toont hij offline.html in plaats van de foutpagina van de browser.
// Uitzondering: het trainingsscherm (/sessions/{id}) wordt bewaard, zodat het ook zonder bereik opent
// (resources/js/offline-training.js zet daar wat je zonder bereik deed weer op). Daarvoor worden ook
// de scripts, styles en plaatjes bewaard. Bij het inloggen/uitloggen gaat de bewaarde training weg.
// Ophogen als offline.html verandert, anders houden telefoons de oude versie
const CACHE = 'lukeboy-v3';
const PAGES = 'lukeboy-pages';
const ASSETS = 'lukeboy-assets';
const OFFLINE = '/offline.html';
const TRAINING = /^\/sessions\/\d+$/;
// Langzaam bereik in de sportschool: na zo lang de bewaarde training tonen
const SLOW = 4000;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE, '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    const keep = [CACHE, PAGES, ASSETS];
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => !keep.includes(key)).map((key) => caches.delete(key)))));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        if (url.pathname === '/login' || url.pathname === '/logout') event.waitUntil(caches.delete(PAGES));
        event.respondWith(TRAINING.test(url.pathname) ? training(event, url.pathname) : fetch(request).catch(() => caches.match(OFFLINE)));
        return;
    }

    if (['script', 'style', 'font', 'image'].includes(request.destination)) {
        event.respondWith(asset(event));
    }
});

// Trainingsscherm: van de server en bewaren; geen (of traag) bereik: de bewaarde versie
async function training(event, path) {
    const cache = await caches.open(PAGES);
    const network = fetch(event.request).then(async (response) => {
        if (response.ok && !response.redirected) {
            await cache.put(path, response.clone());
        } else if (response.redirected) {
            await cache.delete(path); // training afgerond: naar de samenvatting
        }
        return response;
    });
    event.waitUntil(network.catch(() => null));

    const saved = await cache.match(path);
    if (!saved) return network.catch(() => caches.match(OFFLINE));

    const slow = new Promise((resolve) => setTimeout(() => resolve(saved), SLOW));
    return Promise.race([network.catch(() => saved), slow]);
}

// Scripts, styles en plaatjes: uit de telefoon als dat kan, en op de achtergrond verversen
async function asset(event) {
    const cache = await caches.open(ASSETS);
    const saved = await cache.match(event.request);
    const network = fetch(event.request).then((response) => {
        if (response.ok) cache.put(event.request, response.clone());
        return response;
    });
    if (saved) {
        event.waitUntil(network.catch(() => null));
        return saved;
    }
    return network;
}

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
