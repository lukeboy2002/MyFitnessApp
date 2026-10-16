<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Verwijderen via een bevestigingsmodal (<x-confirm-delete />).
 * Het component definieert zelf delete(int $id) en deletableModel().
 */
trait ConfirmsDeletion
{
    public bool $confirmingDeletion = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    /** @return class-string<Model> */
    abstract protected function deletableModel(): string;

    abstract public function delete(int $id): void;

    public function confirmDelete(int $id): void
    {
        $model = $this->deletableModel()::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('delete', $model);

        $this->deletingId = $model->id;
        $this->deletingName = $model->name;
        $this->confirmingDeletion = true;
    }

    public function deleteConfirmed(): void
    {
        $id = $this->deletingId;
        $this->cancelDelete();

        if ($id) {
            $this->delete($id);
        }
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeletion = false;
        $this->reset('deletingId', 'deletingName');
    }
}
