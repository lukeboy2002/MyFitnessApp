{{--
    Wrapper die label + hint + fout om een willekeurig veld zet.
    Wordt intern gebruikt door input, textarea, select, password; zelf te gebruiken voor eigen velden:
    <x-form.field label="Kleur" name="color"> <input type="color" ...> </x-form.field>
--}}
@props(['label' => null, 'name' => null, 'id' => null, 'hint' => null, 'required' => false])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-col gap-1.5']) }}>
    @if ($label)
        <x-form.label :for="$id" :required="$required">{{ $label }}</x-form.label>
    @endif

    {{ $slot }}

    @if ($hint && ! ($name && $errors->has($name)))
        <x-form.hint :id="$id ? $id.'-hint' : null">{{ $hint }}</x-form.hint>
    @endif
    @if ($name)
        <x-form.error :name="$name" :id="$id ? $id.'-error' : null" />
    @endif
</div>
