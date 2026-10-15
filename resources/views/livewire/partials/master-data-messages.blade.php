@if ($message)
    <x-alert type="success" class="mb-5" wire:key="msg-{{ md5($message) }}">{{ $message }}</x-alert>
@endif
@if ($error)
    <x-alert type="danger" class="mb-5" wire:key="err-{{ md5($error) }}">{{ $error }}</x-alert>
@endif
