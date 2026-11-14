<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Per soort pushmelding aan/uit (geldt voor al je apparaten); opslaan bij omzetten */
class PushPreferences extends Component
{
    public bool $push_rest = true;

    public bool $push_admin = true;

    public function mount(): void
    {
        $this->push_rest = Auth::user()->push_rest;
        $this->push_admin = Auth::user()->push_admin;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['push_rest', 'push_admin'], true)) {
            Auth::user()->forceFill([$property => $this->{$property}])->save();
            $this->dispatch('saved');
        }
    }

    public function render()
    {
        return view('livewire.profile.push-preferences');
    }
}
