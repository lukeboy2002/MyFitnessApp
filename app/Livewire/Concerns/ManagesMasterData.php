<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Locked;

/**
 * Gedeeld gedrag voor de beheerpagina's van stamgegevens:
 * formulier in een modal, aanmaken/bewerken, plaatje en meldingen.
 */
trait ManagesMasterData
{
    use HandlesImageUpload;

    /** Iconen om uit te kiezen (zie components/icon.blade.php) */
    public const ICONS = ['arm' => 'Arm', 'body' => 'Body', 'dumbbell' => 'Dumbbell', 'fiber' => 'Muscle', 'run' => 'Running', 'heart' => 'Heart', 'bolt' => 'Strength'];

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public ?string $message = null;

    public ?string $error = null;

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
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
}
