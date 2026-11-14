<div>
    <x-page-header :title="__('Settings')" :description="__('Settings for the whole app. Only admins see this page.')" />

    {{-- Site offline --}}
    @php($offline = \App\Support\Offline::isOn())
    <x-card class="mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <span @class(['grid size-11 shrink-0 place-items-center rounded-xl',
                          'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => ! $offline,
                          'bg-red-50 text-red-700 dark:bg-red-400/15 dark:text-red-300' => $offline])>
                <x-icon :name="$offline ? 'lock' : 'check'" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ $offline ? __('The site is offline') : __('The site is online') }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $offline ? __('Only admins can use the app; everyone else sees the message below.') : __('Everyone can use the app. When offline, only admins see the app.') }}
                </p>
            </div>
            <x-button :variant="$offline ? 'primary' : 'danger'" type="button" :icon="$offline ? 'check' : 'lock'" wire:click="toggleOffline" loading="toggleOffline"
                      wire:confirm="{{ $offline ? __('Put the site back online?') : __('Take the site offline? Only admins can get in then.') }}">
                {{ $offline ? __('Put online') : __('Take offline') }}
            </x-button>
        </div>
        <x-form.textarea :label="__('Message for visitors')" wire:model="offlineMessage" rows="2" class="mt-4" :hint="__('Saved when you press the button')" />
    </x-card>

    {{-- Registratie --}}
    @php($open = \App\Support\Registration::isOpen())
    <x-card class="mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <span @class(['grid size-11 shrink-0 place-items-center rounded-xl',
                          'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => $open,
                          'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300' => ! $open])>
                <x-icon :name="$open ? 'user' : 'lock'" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ $open ? __('Registration is open') : __('Registration is closed (status)') }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $open ? __('Anyone can create an account via the login page.') : __('Only existing accounts can log in; the Register button is hidden.') }}
                </p>
            </div>
            <x-button :variant="$open ? 'secondary' : 'primary'" type="button" :icon="$open ? 'lock' : 'user'" wire:click="toggleRegistration"
                      wire:confirm="{{ $open ? __('Close registration?') : __('Open registration? Then anyone can create an account.') }}">
                {{ $open ? __('Close (registration)') : __('Open (registration)') }}
            </x-button>
        </div>
    </x-card>

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- Mededeling --}}
        <x-card :title="__('Announcement')" :description="__('A message at the top of every page for all users, e.g. about maintenance or something new.')">
            <form wire:submit="saveAnnouncement" id="announcement-form" class="grid gap-4">
                <x-form.textarea :label="__('Text')" wire:model="announcement" rows="3" :hint="__('Leave empty = no announcement')" />
                <x-form.select :label="__('Type (kind)')" wire:model="announcementType" :options="['info' => __('Information (blue)'), 'warning' => __('Important (yellow)')]" />
            </form>

            <x-slot:footer>
                <x-action-message on="announcement-saved">{{ __('Saved.') }}</x-action-message>
                <x-button form="announcement-form" icon="check" loading="saveAnnouncement">{{ __('Save') }}</x-button>
            </x-slot:footer>
        </x-card>

        {{-- Standaardwaarden --}}
        <x-card :title="__('Defaults')" :description="__('Starting values for new items. Existing workouts and accounts do not change.')">
            <form wire:submit="saveDefaults" id="defaults-form" class="grid gap-4">
                <x-form.input :label="__('Rest between sets')" wire:model="restSeconds" type="number" min="0" max="900" :suffix="__('sec')" :hint="__('For an exercise you add to a workout or training')" />
                <x-form.input :label="__('Weekly goal for new accounts')" wire:model="weeklyGoalHours" :suffix="__('hours')" inputmode="decimal" :placeholder="__('e.g. :value', ['value' => '3 / 4,5'])" :hint="__('Leave empty = no weekly goal')" />
            </form>

            <x-slot:footer>
                <x-action-message on="defaults-saved">{{ __('Saved.') }}</x-action-message>
                <x-button form="defaults-form" icon="check" loading="saveDefaults">{{ __('Save') }}</x-button>
            </x-slot:footer>
        </x-card>
    </div>

    {{-- Voorbeelden --}}
    <x-card :title="__('Mails and error pages')" :description="__('View them as a user sees them. Nothing is sent; the mails use your account as an example.')" class="mt-5">
        <div class="grid gap-x-8 gap-y-6 lg:grid-cols-2">
            <div>
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Mails') }}</h3>
                <ul class="divide-y divide-slate-100 text-sm dark:divide-line-dark">
                    @foreach (\App\Http\Controllers\Admin\PreviewController::MAILS as $key => $label)
                        <li>
                            <a href="{{ route('admin.preview.mail', $key) }}" target="_blank" class="flex items-center gap-3 py-2 font-semibold text-brand-700 hover:underline dark:text-brand-300">
                                <x-icon name="mail" class="size-4.5 shrink-0" /> {{ __($label) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Error pages') }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Http\Controllers\Admin\PreviewController::ERRORS as $code)
                        <x-button variant="secondary" size="sm" :href="route('admin.preview.error', $code)" target="_blank">{{ $code }}</x-button>
                    @endforeach
                </div>
            </div>
        </div>
    </x-card>

    {{-- Systeem --}}
    <x-card :title="__('System (admin)')" :description="__('How the app is running now.')" class="mt-5">
        @if (app()->isProduction() && config('app.debug'))
            <x-alert type="danger" :dismissible="false" class="mb-4">{{ __('Debug is on on the server. Set APP_DEBUG=false in .env and run php artisan optimize.') }}</x-alert>
        @endif
        @unless (is_link(public_path('storage')))
            <x-alert type="warning" class="mb-4">{{ __('Images are not shown: run php artisan storage:link.') }}</x-alert>
        @endunless

        <div class="grid gap-x-8 gap-y-6 lg:grid-cols-2">
            @foreach (['Software' => $this->system, __('Counts') => $this->totals] as $heading => $rows)
                <div>
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $heading }}</h3>
                    <dl class="divide-y divide-slate-100 text-sm dark:divide-line-dark">
                        @foreach ($rows as $label => $value)
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="truncate text-right font-semibold tabular-nums">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>

        <x-slot:footer>
            <p class="mr-auto text-sm text-slate-500 dark:text-slate-400">{{ __('Clearing the cache helps when a change does not show up.') }}</p>
            <x-action-message on="cache-cleared">{{ __('Cache cleared.') }}</x-action-message>
            <x-button variant="secondary" type="button" icon="trash" wire:click="clearCache" wire:confirm="{{ __('Clear cache?') }}" loading="clearCache">{{ __('Clear cache') }}</x-button>
        </x-slot:footer>
    </x-card>
</div>
