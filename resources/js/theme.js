// Thema: 'light' | 'dark' | 'system'. Livewire levert Alpine mee.
document.addEventListener('alpine:init', () => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');

    Alpine.store('theme', {
        mode: localStorage.getItem('theme') ?? 'system',

        init() {
            media.addEventListener('change', () => this.apply());
            this.apply();
        },

        set(mode) {
            this.mode = mode;
            localStorage.setItem('theme', mode);
            this.apply();
        },

        cycle() {
            const modes = ['light', 'dark', 'system'];
            this.set(modes[(modes.indexOf(this.mode) + 1) % modes.length]);
        },

        get isDark() {
            return this.mode === 'dark' || (this.mode === 'system' && media.matches);
        },

        apply() {
            document.documentElement.classList.toggle('dark', this.isDark);
        },
    });
});

// Na wire:navigate opnieuw toepassen
document.addEventListener('livewire:navigated', () => window.Alpine?.store('theme')?.apply());

// Inloggen met passkey (knop op de loginpagina met data-passkey-login="/dashboard")
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-passkey-login]');
    if (!button) return;

    button.disabled = true;
    try {
        await Passkeys.verify();
        window.location.assign(button.dataset.passkeyLogin || '/dashboard');
    } catch (error) {
        document.getElementById('passkey-error')?.removeAttribute('hidden');
        button.disabled = false;
    }
});
