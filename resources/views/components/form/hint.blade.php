@props(['id' => null])

<p @if ($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'text-sm text-slate-500 dark:text-slate-400']) }}>{{ $slot }}</p>
