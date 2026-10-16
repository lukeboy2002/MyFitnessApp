<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

/**
 * Plaatje uploaden, vervangen of weghalen. Bestanden staan op de public disk (storage/app/public).
 * Het model heeft een kolom image_path.
 */
trait HandlesImageUpload
{
    use WithFileUploads;

    /** Nieuw plaatje uit het formulier */
    public $image = null;

    /** Huidig plaatje van het item dat je bewerkt */
    public ?string $currentImage = null;

    public bool $removeImage = false;

    /** Meteen controleren, zodat een pdf o.i.d. niet als preview getoond wordt */
    public function updatedImage(): void
    {
        try {
            $this->validateOnly('image', $this->imageRules(), attributes: ['image' => 'plaatje']);
        } catch (ValidationException $e) {
            $this->reset('image');

            throw $e;
        }
    }

    public function deleteImage(): void
    {
        $this->reset('image', 'currentImage');
        $this->removeImage = true;
    }

    protected function imageRules(): array
    {
        return ['image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']];
    }

    /** Nieuw plaatje opslaan of het oude weghalen; de oude file wordt van de disk gewist */
    protected function applyImage(Model $model, string $folder): void
    {
        $old = $model->image_path;

        if ($this->image) {
            $model->image_path = $this->image->storePublicly($folder, 'public');
        } elseif ($this->removeImage) {
            $model->image_path = null;
        }

        if ($old && $old !== $model->image_path) {
            Storage::disk('public')->delete($old);
        }
    }

    protected function deleteStoredImage(Model $model): void
    {
        if ($model->image_path) {
            Storage::disk('public')->delete($model->image_path);
        }
    }
}
