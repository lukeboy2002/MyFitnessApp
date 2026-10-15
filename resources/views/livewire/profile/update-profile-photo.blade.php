<x-card title="Profielfoto" description="JPG, PNG of WEBP, maximaal 2 MB.">
    <x-form.image-upload wire:model="photo" shape="round" :hint="null"
                         :preview="$photo?->temporaryUrl() ?? ($user->profile_photo_path ? Storage::url($user->profile_photo_path) : null)"
                         :remove="$user->profile_photo_path ? 'deletePhoto' : null" />
</x-card>
