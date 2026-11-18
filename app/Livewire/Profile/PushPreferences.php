<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Per soort pushmelding aan/uit (geldt voor al je apparaten); opslaan bij omzetten */
class PushPreferences extends Component
{
    public bool $push_rest = true;

    public bool $push_admin = true;

    public bool $push_reminder = true;

    public bool $push_weekly = true;

    public bool $push_inactive = true;

    public bool $push_buddies = true;

    public function mount(): void
    {
        $this->push_rest = Auth::user()->push_rest;
        $this->push_admin = Auth::user()->push_admin;
        $this->push_reminder = Auth::user()->push_reminder;
        $this->push_weekly = Auth::user()->push_weekly;
        $this->push_inactive = Auth::user()->push_inactive;
        $this->push_buddies = Auth::user()->push_buddies;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['push_rest', 'push_admin', 'push_reminder', 'push_weekly', 'push_inactive', 'push_buddies'], true)) {
            Auth::user()->forceFill([$property => $this->{$property}])->save();
            $this->dispatch('saved');
        }
    }

    public function render()
    {
        return view('livewire.profile.push-preferences');
    }
}
