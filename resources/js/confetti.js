// Confetti bij een nieuw record: window.confetti() of het Livewire-event "record".
// Zonder extra pakket: een canvas over het scherm dat zichzelf na een paar seconden opruimt.
const COLORS = ['#ffbc04', '#f97316', '#1d63c5', '#c026d3', '#0e9f8a', '#ffffff'];

window.confetti = (pieces = 160) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:100';
    document.body.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const scale = window.devicePixelRatio || 1;
    canvas.width = innerWidth * scale;
    canvas.height = innerHeight * scale;
    ctx.scale(scale, scale);

    // Twee kanonnen linksonder en rechtsonder die schuin omhoog schieten
    const particles = Array.from({ length: pieces }, (_, i) => {
        const left = i % 2 === 0;
        const angle = (left ? -60 : -120) + (Math.random() * 40 - 20);
        const speed = 9 + Math.random() * 9;

        return {
            x: left ? 0 : innerWidth,
            y: innerHeight * 0.85,
            vx: Math.cos(angle * Math.PI / 180) * speed,
            vy: Math.sin(angle * Math.PI / 180) * speed,
            size: 6 + Math.random() * 6,
            color: COLORS[i % COLORS.length],
            spin: Math.random() * Math.PI,
            spinSpeed: (Math.random() - 0.5) * 0.3,
        };
    });

    const start = performance.now();
    const frame = (now) => {
        const elapsed = now - start;
        ctx.clearRect(0, 0, innerWidth, innerHeight);
        ctx.globalAlpha = Math.max(0, 1 - Math.max(0, elapsed - 2500) / 1000);

        for (const p of particles) {
            p.vy += 0.25;          // zwaartekracht
            p.vx *= 0.99;          // luchtweerstand
            p.x += p.vx;
            p.y += p.vy;
            p.spin += p.spinSpeed;

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.spin);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2 * Math.abs(Math.cos(p.spin * 2)) + 1);
            ctx.restore();
        }

        elapsed < 3500 ? requestAnimationFrame(frame) : canvas.remove();
    };
    requestAnimationFrame(frame);
};

// Livewire: $this->dispatch('record', title: ..., text: ...)
window.addEventListener('record', () => {
    window.confetti();
    navigator.vibrate?.([100, 50, 100, 50, 200]);
});
