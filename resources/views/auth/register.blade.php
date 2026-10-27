@php
    // Bij een validatiefout opnieuw openen op de stap waar de fout zit
    use App\Enums\ExperienceLevel;use App\Enums\FitnessGoal;use App\Enums\Gender;use App\Models\Setting;$steps = [
        1 => ['label' => 'Account', 'fields' => ['name', 'username', 'email', 'password']],
        2 => ['label' => 'Over jou', 'fields' => ['birth_date', 'gender', 'height_cm', 'experience']],
        3 => ['label' => 'Je doel', 'fields' => ['weight_kg', 'target_weight_kg', 'goal', 'weekly_goal_hours']],
    ];
    $start = collect($steps)->search(fn ($step) => $errors->hasAny($step['fields'])) ?: 1;
    $defaultGoal = Setting::get('default_weekly_goal_minutes');
@endphp

<x-guest-layout title="Account maken" subtitle="Na registratie ontvang je een mail om je e-mailadres te bevestigen.">
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
            <x-form.input label="Volledige naam" name="name" icon="user" :value="old('name')" required autofocus
                          autocomplete="name"/>
            <div>
                <x-form.input label="Gebruikersnaam" name="username" icon="user" :value="old('username')" required
                              autocomplete="username" x-on:input.debounce.400ms="checkUsername($event.target)"/>
                <p x-show="username.message" x-cloak x-text="username.message" aria-live="polite"
                   x-bind:class="username.available ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                   class="mt-1.5 text-sm font-semibold"></p>
            </div>
            <x-form.input label="E-mailadres" type="email" name="email" icon="mail" :value="old('email')" required
                          autocomplete="username"/>
            <x-form.password label="Wachtwoord" name="password" required autocomplete="new-password"
                             hint="Minimaal 8 tekens"/>
            <x-form.password label="Wachtwoord bevestigen" name="password_confirmation" required
                             autocomplete="new-password"/>
            <x-button type="button" block size="lg" icon="chevron-right" x-on:click="next()">Volgende</x-button>
        </div>

        {{-- 2. Over jou --}}
        <div x-ref="step2" x-show="step === 2" x-cloak class="flex flex-col gap-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">Hiermee berekent de app je BMI en hartslagzones. Alles
                is optioneel en later aan te passen in je profiel.</p>
            <x-form.input label="Geboortedatum" type="date" name="birth_date" :value="old('birth_date')"
                          :max="now()->subDay()->format('Y-m-d')"/>
            <div class="grid grid-cols-2 gap-4">
                <x-form.select label="Geslacht" name="gender" :options="Gender::options()"
                               placeholder="Zeg ik niet"/>
                <x-form.input label="Lengte" type="number" name="height_cm" :value="old('height_cm')"
                              inputmode="numeric" suffix="cm" placeholder="bv. 180" min="100" max="250"/>
            </div>
            <x-form.select label="Ervaring" name="experience" :options="ExperienceLevel::options()"
                           placeholder="Kies…"/>
            <div class="flex gap-3">
                <x-button type="button" variant="secondary" size="lg" icon="chevron-left" x-on:click="step = 1"
                          aria-label="Vorige"/>
                <x-button type="button" block size="lg" icon="chevron-right" x-on:click="next()">Volgende</x-button>
            </div>
        </div>

        {{-- 3. Je doel --}}
        <div x-ref="step3" x-show="step === 3" x-cloak class="flex flex-col gap-5">
            <x-form.select label="Waar train je voor?" name="goal" :options="FitnessGoal::options()"
                           placeholder="Kies je doel…"/>
            <div class="grid grid-cols-2 gap-4">
                <x-form.input label="Gewicht nu" name="weight_kg" :value="old('weight_kg')" inputmode="decimal"
                              suffix="kg" placeholder="bv. 84,2"/>
                <x-form.input label="Streefgewicht" name="target_weight_kg" :value="old('target_weight_kg')"
                              inputmode="decimal" suffix="kg" placeholder="optioneel"/>
            </div>
            <x-form.input label="Trainingstijd per week" name="weekly_goal_hours" inputmode="decimal" suffix="uur"
                          :value="old('weekly_goal_hours', $defaultGoal ? str_replace('.', ',', (string) round($defaultGoal / 60, 2)) : '')"
                          placeholder="bv. 4,5" hint="Je weekdoel op het dashboard"/>
            <div class="flex gap-3">
                <x-button type="button" variant="secondary" size="lg" icon="chevron-left" x-on:click="step = 2"
                          aria-label="Vorige"/>
                <x-button block size="lg" icon="check">Account maken</x-button>
            </div>
        </div>
    </form>

    <x-slot:footer>
        Al een account? <a href="{{ route('login') }}"
                           class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Inloggen</a>
    </x-slot:footer>
</x-guest-layout>
