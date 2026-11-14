{{-- Profiel > Training: pushmeldingen als je rust voorbij is, per apparaat aan/uit (resources/js/push.js) --}}
<x-card :title="__('Notification after your rest')" :description="__('A signal when your rest is over, even when your screen is off. Applies to this device.')">
    <div x-data="{
            state: 'loading',
            busy: false,
            note: '',
            async init() { this.state = await window.LukeboyPush?.state() ?? 'unsupported' },
            async run(action, done = '') {
                this.busy = true; this.note = '';
                try { const result = await window.LukeboyPush[action](); if (typeof result === 'string') this.state = result; this.note = done }
                catch (e) { this.note = {{ Js::from(__('That did not work. Please try again.')) }} }
                this.busy = false;
            },
        }" class="flex flex-col gap-4">
        <div class="flex items-start gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-xl"
                  :class="state === 'on' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300'">
                <x-icon name="clock" />
            </span>
            <p class="min-w-0 flex-1 text-sm text-slate-600 dark:text-slate-300">
                <span x-show="state === 'loading'">{{ __('Checking…') }}</span>
                <span x-show="state === 'on'" x-cloak><strong class="text-ink dark:text-white">{{ __('On for this device.') }}</strong> {{ __('You will get a notification when your rest is over. Your phone then vibrates according to your own settings.') }}</span>
                <span x-show="state === 'off'" x-cloak>{{ __('Off. Turn it on and your phone will ask whether notifications are allowed.') }}</span>
                <span x-show="state === 'denied'" x-cloak>{{ __('You have blocked notifications for this app. Turn them on in the settings of your phone or browser and then come back.') }}</span>
                <span x-show="state === 'install'" x-cloak>{!! __('On an iPhone this only works from the app on your home screen. In Safari, tap <strong>Share</strong> and then <strong>Add to Home Screen</strong>, open the app there and turn it on here.') !!}</span>
                <span x-show="state === 'unsupported'" x-cloak>{{ __('This browser does not support push notifications. You will hear a beep as long as the app is open.') }}</span>
                <span x-show="state === 'unconfigured'" x-cloak>{{ __('Push notifications have not been set up on the server yet (keys in .env).') }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2" x-show="['on', 'off'].includes(state)" x-cloak>
            <x-button type="button" icon="check" x-show="state === 'off'" x-on:click="run('enable', {{ Js::from(__('Notifications are on.')) }})" x-bind:disabled="busy">{{ __('Turn on notifications') }}</x-button>
            <x-button variant="secondary" type="button" icon="play" x-show="state === 'on'" x-on:click="run('test', {{ Js::from(__('Test notification sent.')) }})" x-bind:disabled="busy">{{ __('Send a test notification') }}</x-button>
            <x-button variant="ghost" type="button" x-show="state === 'on'" x-on:click="run('disable', {{ Js::from(__('Notifications are off.')) }})" x-bind:disabled="busy">{{ __('Turn off') }}</x-button>
            <span class="text-sm text-slate-500 dark:text-slate-400" x-text="note"></span>
        </div>
    </div>
</x-card>
