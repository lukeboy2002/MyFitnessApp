<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

/**
 * Gedeeld gedrag voor de beheerpagina's van stamgegevens:
 * formulier in een modal, aanmaken/bewerken, plaatje en meldingen.
 */
trait ManagesMasterData
{
    use WithFileUploads;

    /** Iconen om uit te kiezen (zie components/icon.blade.php) */
    public const ICONS = ['arm' => 'Arm', 'body' => 'Lichaam', 'dumbbell' => 'Dumbbell', 'fiber' => 'Spier', 'run' => 'Hardlopen', 'heart' => 'Hart', 'bolt' => 'Kracht'];

    /** Nieuw plaatje uit het formulier */
    public $image = null;

    /** Huidig plaatje van het item dat je bewerkt */
    public ?string $currentImage = null;

    public bool $removeImage = false;

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?string $message = null;

    public ?string $error = null;

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

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

    protected function openForm(?int $id = null, ?string $currentImage = null): void
    {
        $this->resetValidation();
        $this->reset('image', 'removeImage');
        $this->currentImage = $currentImage;
        $this->editingId = $id;
        $this->message = $this->error = null;
        $this->showForm = true;
    }

    protected function done(string $message): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->error = null;
        $this->message = $message;
    }

    protected function fail(string $error): void
    {
        $this->message = null;
        $this->error = $error;
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
