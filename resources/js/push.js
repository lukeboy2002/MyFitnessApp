// Pushmeldingen (rust voorbij) aan/uit voor dit apparaat, plus een korte piep in de app.
// Werkt in Chrome/Edge/Firefox/Android, en op een iPhone (iOS 16.4+) alleen als de app op het beginscherm staat.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const vapidKey = () => document.querySelector('meta[name="vapid-public-key"]')?.content;

function keyToBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

async function send(method, url, body) {
    const response = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
}

// Een AudioContext mag pas na een tik geluid maken (vooral op iOS); bij de eerste tik op de pagina vrijgeven
let audio = null;
document.addEventListener('pointerdown', () => {
    audio ??= new (window.AudioContext || window.webkitAudioContext)();
    audio.resume?.();
}, { passive: true });

window.LukeboyPush = {
    isIos: () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),
    isStandalone: () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
    supported: () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,

    /** 'unconfigured' | 'install' (iPhone: eerst op beginscherm) | 'unsupported' | 'denied' | 'on' | 'off' */
    async state() {
        if (!vapidKey()) return 'unconfigured';
        if (!this.supported()) return this.isIos() && !this.isStandalone() ? 'install' : 'unsupported';
        if (Notification.permission === 'denied') return 'denied';
        const registration = await navigator.serviceWorker.ready;
        return (await registration.pushManager.getSubscription()) ? 'on' : 'off';
    },

    async enable() {
        if ((await Notification.requestPermission()) !== 'granted') return this.state();
        const registration = await navigator.serviceWorker.ready;
        const subscription = (await registration.pushManager.getSubscription())
            ?? (await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(vapidKey()) }));
        await send('POST', '/push/subscription', {
            ...subscription.toJSON(),
            contentEncoding: (PushManager.supportedContentEncodings ?? ['aes128gcm'])[0],
        });
        return 'on';
    },

    async disable() {
        const subscription = await (await navigator.serviceWorker.ready).pushManager.getSubscription();
        if (subscription) {
            await send('DELETE', '/push/subscription', { endpoint: subscription.endpoint });
            await subscription.unsubscribe();
        }
        return 'off';
    },

    test: () => send('POST', '/push/test'),

    /** Twee korte piepjes */
    beep() {
        if (!audio || audio.state !== 'running') return;
        [0, 0.25].forEach((start) => {
            const tone = audio.createOscillator();
            const volume = audio.createGain();
            tone.frequency.value = 880;
            volume.gain.setValueAtTime(0.25, audio.currentTime + start);
            volume.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + start + 0.18);
            tone.connect(volume).connect(audio.destination);
            tone.start(audio.currentTime + start);
            tone.stop(audio.currentTime + start + 0.2);
        });
    },
};
