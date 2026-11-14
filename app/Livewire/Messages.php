<?php

namespace App\Livewire;

use App\Models\UserMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Berichten van de admin: lezen, als gelezen markeren en verwijderen. ?read={id} (uit de pushmelding) zet dat bericht op gelezen */
#[Layout('layouts.app')]
class Messages extends Component
{
    #[Url(except: null)]
    public ?int $read = null;

    public function mount(): void
    {
        if ($this->read) {
            $this->find($this->read)?->markRead();
        }
    }

    #[Computed]
    public function messages()
    {
        return Auth::user()->messages()->latest('id')->limit(100)->get();
    }

    public function markRead(int $id): void
    {
        $this->find($id)?->markRead();
        $this->refresh();
    }

    public function markUnread(int $id): void
    {
        $this->find($id)?->update(['read_at' => null]);
        $this->refresh();
    }

    public function markAllRead(): void
    {
        Auth::user()->messages()->whereNull('read_at')->update(['read_at' => now()]);
        $this->refresh();
    }

    public function delete(int $id): void
    {
        $this->find($id)?->delete();
        $this->refresh();
    }

    private function find(int $id): ?UserMessage
    {
        return Auth::user()->messages()->find($id);
    }

    /** Lijst en de teller bij het belletje bijwerken */
    private function refresh(): void
    {
        unset($this->messages);
        $this->dispatch('messages-changed', unread: Auth::user()->messages()->whereNull('read_at')->count());
    }

    public function render()
    {
        return view('livewire.messages')->title(__('Messages'));
    }
}
