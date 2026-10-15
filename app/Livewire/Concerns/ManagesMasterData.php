<?php

namespace App\Livewire\Concerns;

/**
 * Gedeeld gedrag voor de beheerpagina's van stamgegevens:
 * formulier in een modal, aanmaken/bewerken en meldingen.
 */
trait ManagesMasterData
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?string $message = null;

    public ?string $error = null;

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    protected function openForm(?int $id = null): void
    {
        $this->resetValidation();
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
