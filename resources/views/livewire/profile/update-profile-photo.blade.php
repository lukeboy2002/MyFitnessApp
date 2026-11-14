<x-card :title="__('Profile photo')" :description="__('JPG, PNG or WEBP, up to :size MB.', ['size' => 2])">
    <x-form.image-upload wire:model="photo" shape="round" :hint="null"
                         :preview="$photo?->temporaryUrl() ?? ($user->profile_photo_path ? Storage::url($user->profile_photo_path) : null)"
                         :remove="$user->profile_photo_path ? 'deletePhoto' : null" />
</x-card>
