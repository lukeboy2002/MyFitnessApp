{{--
    Datumveld: intypen als dd-mm-jjjj (de streepjes komen vanzelf) of kiezen met de kalenderknop.
    De waarde is altijd jjjj-mm-dd, net als bij <input type="date">.
    <x-form.date label="Datum" wire:model="date" :max="today()->format('Y-m-d')" />
    <x-form.date label="Geboortedatum" name="birth_date" :value="old('birth_date')" />  (gewoon formulier)
--}}
@props(['label' => null, 'name' => null, 'id' => null, 'hint' => null, 'required' => false, 'value' => null, 'min' => null, 'max' => null])

@php
    $model = $attributes->whereStartsWith('wire:model')->first();
    $name ??= $model;
    $id ??= $name ? 'f-'.str($name)->replace(['.', '[', ']'], '-')->trim('-') : null;
    $invalid = $name && $errors->has($name);
    $c = config('ui.control');
@endphp

<x-form.field :$label :$name :$id :$hint :$required :class="$attributes->get('class')">
    <div {{ $attributes->whereStartsWith('wire:model') }} x-modelable="value" class="relative"
         x-data="{
             value: @js((string) $value),
             text: '',
             min: @js($min),
             max: @js($max),
             init() {
                 this.text = this.display(this.value);
                 this.$watch('value', value => {
                     if (this.parse(this.text) !== value) this.text = this.display(value);
                 });
             },
             // jjjj-mm-dd -> dd-mm-jjjj
             display(value) {
                 const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
                 return match ? `${match[3]}-${match[2]}-${match[1]}` : '';
             },
             // dd-mm-jjjj (ook 1-3-1972, 01/03/72 of 01.03.1972) -> jjjj-mm-dd, of null
             parse(text) {
                 const parts = (text || '').trim().split(/\D+/).filter(Boolean);
                 if (parts.length !== 3 || parts[2].length === 1 || parts[2].length === 3) return null;
                 let [day, month, year] = parts.map(Number);
                 if (parts[2].length === 2) year += year > new Date().getFullYear() % 100 ? 1900 : 2000;
                 const date = new Date(year, month - 1, day);
                 if (year < 1900 || date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;
                 return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
             },
             typed(input) {
                 let text = input.value;
                 // Alleen cijfers: zelf de streepjes zetten (14031972 -> 14-03-1972)
                 if (/^[\d-]*$/.test(text)) {
                     const digits = text.replace(/\D/g, '').slice(0, 8);
                     text = digits.length > 4 ? `${digits.slice(0, 2)}-${digits.slice(2, 4)}-${digits.slice(4)}`
                         : digits.length > 2 ? `${digits.slice(0, 2)}-${digits.slice(2)}` : digits;
                 }
                 this.text = text;
                 const value = this.parse(text);
                 if (text === '') this.value = '';
                 else if (value !== null) this.value = value;
                 this.check(input, value, false);
             },
             check(input, value, done) {
                 let message = '';
                 if (this.text && value === null && (done || this.text.replace(/\D/g, '').length >= 8)) message = 'Vul een bestaande datum in als dd-mm-jjjj.';
                 else if (value && this.max && value > this.max) message = 'Deze datum ligt te laat.';
                 else if (value && this.min && value < this.min) message = 'Deze datum ligt te vroeg.';
                 input.setCustomValidity(message);
             },
             left(input) {
                 const value = this.parse(this.text);
                 if (value) this.text = this.display(value);
                 this.check(input, value, true);
             },
             picked(picker) {
                 this.value = picker.value;
                 this.text = this.display(picker.value);
                 this.$refs.text.setCustomValidity('');
             },
         }">
        <input type="text" x-ref="text" inputmode="numeric" placeholder="dd-mm-jjjj" autocomplete="off" maxlength="10"
               @if ($id) id="{{ $id }}" @endif
               @if ($required) required @endif
               @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
               x-bind:value="text" x-on:input="typed($el)" x-on:blur="left($el)"
               {{ $attributes->except('class')->whereDoesntStartWith('wire:model')->class([$c['base'], 'pr-12 tabular-nums', $c['invalid'] => $invalid]) }}>

        @if ($name && ! $model)
            <input type="hidden" name="{{ $name }}" x-bind:value="value">
        @endif

        {{-- Kalenderknop: een onzichtbaar datumveld erover, zodat telefoons hun eigen kiezer openen --}}
        <span class="absolute right-1 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-lg text-slate-400 hover:text-ink dark:hover:text-white">
            <x-icon name="calendar" class="size-5" />
            <input type="date" tabindex="-1" aria-label="Kies een datum" class="absolute inset-0 size-full cursor-pointer opacity-0"
                   @if ($min) min="{{ $min }}" @endif @if ($max) max="{{ $max }}" @endif
                   x-bind:value="value" x-on:click="try { $el.showPicker() } catch (e) {}" x-on:change="picked($el)">
        </span>
    </div>
</x-form.field>
