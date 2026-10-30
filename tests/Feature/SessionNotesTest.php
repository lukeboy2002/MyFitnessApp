<?php

use App\Enums\SessionStatus;
use App\Livewire\Calendar;
use App\Livewire\Dashboard;
use App\Livewire\Sessions\Summary;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->session = WorkoutSession::create([
        'user_id' => $this->user->id, 'name' => 'Push', 'color' => '#00468c', 'status' => SessionStatus::Completed,
        'started_at' => now()->subHours(2), 'ended_at' => now()->subHour(), 'duration_seconds' => 3600,
    ]);
});

test('a note can be added to a finished training and is then fixed', function () {
    $updatedAt = $this->session->updated_at;
    $this->travel(5)->minutes();

    Livewire::actingAs($this->user)->test(Summary::class, ['session' => $this->session])
        ->assertSee('Na het opslaan kun je de notitie niet meer wijzigen.')
        ->set('notes', '  Knie voelde stijf  ')
        ->call('saveNotes')
        ->assertHasNoErrors()
        ->assertSee('“Knie voelde stijf”', false)
        ->assertDontSee('Na het opslaan kun je de notitie niet meer wijzigen.');

    $session = $this->session->fresh();
    expect($session->notes)->toBe('Knie voelde stijf')
        ->and($session->updated_at->equalTo($updatedAt))->toBeTrue();   // badges en records hoeven niet opnieuw

    // Daarna niet meer te wijzigen, ook niet met een eigen verzoek
    Livewire::actingAs($this->user)->test(Summary::class, ['session' => $session])
        ->set('notes', 'Toch anders')
        ->call('saveNotes')
        ->assertForbidden();
    expect($session->fresh()->notes)->toBe('Knie voelde stijf');
});

test('an empty or too long note is not saved', function () {
    Livewire::actingAs($this->user)->test(Summary::class, ['session' => $this->session])
        ->set('notes', '   ')
        ->call('saveNotes')
        ->assertHasErrors('notes')
        ->set('notes', str_repeat('a', 1001))
        ->call('saveNotes')
        ->assertHasErrors('notes');

    expect($this->session->fresh()->notes)->toBeNull();
});

test('nobody else can see or change your note', function () {
    Livewire::actingAs(User::factory()->create())->test(Summary::class, ['session' => $this->session])
        ->assertForbidden();
});

test('the note is shown in the recent trainings and the calendar', function () {
    $this->session->update(['notes' => 'Drukke sportschool']);

    Livewire::actingAs($this->user)->test(Dashboard::class)->assertSee('“Drukke sportschool”', false);
    Livewire::actingAs($this->user)->test(Calendar::class)->call('select', $this->session->started_at->toDateString())
        ->assertSee('“Drukke sportschool”', false);
});
