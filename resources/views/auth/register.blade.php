@php
    // Bij een validatiefout opnieuw openen op de stap waar de fout zit
    use App\Enums\ExperienceLevel;use App\Enums\FitnessGoal;use App\Enums\Gender;use App\Models\Setting;$steps = [
        1 => ['label' => __('Account'), 'fields' => ['name', 'username', 'email', 'password']],
        2 => ['label' => __('About you'), 'fields' => ['birth_date', 'gender', 'height_cm', 'experience']],
        3 => ['label' => __('Your goal'), 'fields' => ['weight_kg', 'target_weight_kg', 'goal', 'weekly_goal_hours']],
    ];
    $start = collect($steps)->search(fn ($step) => $errors->hasAny($step['fields'])) ?: 1;
    $defaultGoal = Setting::get('default_weekly_goal_minutes');
@endphp

<x-guest-layout :title="__('Create account')" :subtitle="__('After registering you will receive an email to confirm your email address.')">
    <form method="POST" action="{{ route('register') }}"
          x-data="{
              step: {{ $start }},
              next() {
                  // Eerst de verplichte velden van deze stap controleren
                  const fields = [...this.$refs['step' + this.step].querySelectorAll('input, select')];
                  if (fields.every(field => field.reportValidity())) {
                      this.step++;
                      this.$nextTick(() => this.$refs['step' + this.step].querySelector('input, select')?.focus());
                  }
              },
              // Gebruikersnaam al bezet? Dan blokkeert de melding de knop Volgende
              username: { message: '', available: null },
              async checkUsername(input) {
                  input.setCustomValidity('');
                  const value = input.value.trim();
                  if (! value) {
                      this.username = { message: '', available: null };
                      return;
                  }
                  const response = await fetch('{{ route('register.username') }}?username=' + encodeURIComponent(value), { headers: { Accept: 'application/json' } });
                  if (! response.ok || input.value.trim() !== value) return;
                  this.username = await response.json();
                  input.setCustomValidity(this.username.available ? '' : this.username.message);
              },
          }"
          x-on:keydown.enter="if (step < 3 && $event.target.tagName === 'INPUT') { $event.preventDefault(); next() }">
        @csrf

        {{-- Stappen --}}
        <ol class="mb-6 grid grid-cols-3 gap-2">
            @foreach ($steps as $number => $step)
                <li>
                    <button type="button" x-on:click="if ({{ $number }} < step) step = {{ $number }}"
                            class="w-full text-left"
                            x-bind:class="{{ $number }} < step ? 'cursor-pointer' : 'cursor-default'">
                        <span class="block h-1.5 rounded-full transition"
                              x-bind:class="{{ $number }} <= step ? 'bg-brand-600 dark:bg-brand-400' : 'bg-slate-200 dark:bg-white/10'"></span>
                        <span class="mt-2 block text-xs font-semibold"
                              x-bind:class="{{ $number }} === step ? 'text-ink dark:text-white' : 'text-slate-500 dark:text-slate-400'">
                            {{ $number }}. {{ $step['label'] }}
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>

        {{-- 1. Account --}}
        <div x-ref="step1" x-show="step === 1" class="flex flex-col gap-5">
            <x-form.input :label="__('Full name')" name="name" icon="user" :value="old('name')" required autofocus
                          autocomplete="name"/>
            <div>
                <x-form.input :label="__('Username')" name="username" icon="user" :value="old('username')" required
                              autocomplete="username" x-on:input.debounce.400ms="checkUsername($event.target)"/>
                <p x-show="username.message" x-cloak x-text="username.message" aria-live="polite"
                   x-bind:class="username.available ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                   class="mt-1.5 text-sm font-semibold"></p>
            </div>
            <x-form.input :label="__('Email address')" type="email" name="email" icon="mail" :value="old('email')" required
                          autocomplete="username"/>
            <x-form.password :label="__('Password')" name="password" required autocomplete="new-password"
                             :hint="__('At least 8 characters')"/>
            <x-form.password :label="__('Confirm password')" name="password_confirmation" required
                             autocomplete="new-password"/>
            <x-button type="button" block size="lg" icon="chevron-right" x-on:click="next()">{{ __('Next') }}</x-button>
        </div>

        {{-- 2. Over jou --}}
        <div x-ref="step2" x-show="step === 2" x-cloak class="flex flex-col gap-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('The app uses this to calculate your BMI and heart rate zones. Everything is optional and can be changed later in your profile.') }}</p>
            <x-form.date :label="__('Date of birth')" name="birth_date" :value="old('birth_date')"
                          :max="now()->subDay()->format('Y-m-d')"/>
            <div class="grid grid-cols-2 gap-4">
                <x-form.select :label="__('Gender')" name="gender" :options="Gender::options()"
                               :placeholder="__('Prefer not to say')"/>
                <x-form.input :label="__('Height')" type="number" name="height_cm" :value="old('height_cm')"
                              inputmode="numeric" suffix="cm" :placeholder="__('e.g. :value', ['value' => 180])" min="100" max="250"/>
            </div>
            <x-form.select :label="__('Experience')" name="experience" :options="ExperienceLevel::options()"
                           :placeholder="__('Choose…')"/>
            <div class="flex gap-3">
                <x-button type="button" variant="secondary" size="lg" icon="chevron-left" x-on:click="step = 1"
                          :aria-label="__('Previous')"/>
                <x-button type="button" block size="lg" icon="chevron-right" x-on:click="next()">{{ __('Next') }}</x-button>
            </div>
        </div>

        {{-- 3. Je doel --}}
        <div x-ref="step3" x-show="step === 3" x-cloak class="flex flex-col gap-5">
            <x-form.select :label="__('What are you training for?')" name="goal" :options="FitnessGoal::options()"
                           :placeholder="__('Choose your goal…')"/>
            <div class="grid grid-cols-2 gap-4">
                <x-form.input :label="__('Current weight')" name="weight_kg" :value="old('weight_kg')" inputmode="decimal"
                              suffix="kg" :placeholder="__('e.g. :value', ['value' => \App\Models\WorkoutExercise::number(84.2)])"/>
                <x-form.input :label="__('Target weight')" name="target_weight_kg" :value="old('target_weight_kg')"
                              inputmode="decimal" suffix="kg" :placeholder="__('optional')"/>
            </div>
            <x-form.input :label="__('Training time per week')" name="weekly_goal_hours" inputmode="decimal" :suffix="__('hours')"
                          :value="old('weekly_goal_hours', $defaultGoal ? \App\Models\WorkoutExercise::number(round($defaultGoal / 60, 2)) : '')"
                          :placeholder="__('e.g. :value', ['value' => \App\Models\WorkoutExercise::number(4.5)])" :hint="__('Your weekly goal on the dashboard')"/>
            <div class="flex gap-3">
                <x-button type="button" variant="secondary" size="lg" icon="chevron-left" x-on:click="step = 2"
                          :aria-label="__('Previous')"/>
                <x-button block size="lg" icon="check">{{ __('Create account') }}</x-button>
            </div>
        </div>
    </form>

    <x-slot:footer>
        {{ __('Already have an account?') }} <a href="{{ route('login') }}"
                           class="font-semibold text-brand-700 hover:underline dark:text-brand-300">{{ __('Log in') }}</a>
    </x-slot:footer>
</x-guest-layout>
