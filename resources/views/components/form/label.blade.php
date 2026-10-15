@props(['for' => null, 'required' => false])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'block text-sm font-semibold text-slate-700 dark:text-slate-200']) }}>
    {{ $slot }}
    @if ($required) <span class="text-fire-500" aria-hidden="true">*</span> @endif
</label>
