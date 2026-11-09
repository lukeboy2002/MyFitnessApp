<?php

use App\Livewire\Exercises\Index;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    foreach (['Bench Press', 'Biceps Curl', 'Chest Press', 'Ëxtra Lunge', '45° Leg Press'] as $name) {
        Exercise::factory()->create(['name' => $name, 'slug' => str($name)->slug()]);
    }
});

test('the letter bar only filters on the first letter', function () {
    Livewire::actingAs($this->user)->test(Index::class)
        ->assertSet('letter', '')
        ->assertSee('Bench Press')->assertSee('Chest Press')
        ->call('pickLetter', 'B')
        ->assertSet('letter', 'B')
        ->assertSee('Bench Press')->assertSee('Biceps Curl')
        ->assertDontSee('Chest Press')
        ->assertSee('2 oefeningen met de B')
        // Nog eens op dezelfde letter = weer alles
        ->call('pickLetter', 'B')
        ->assertSet('letter', '')
        ->assertSee('Chest Press');
});

test('accents count as the letter and numbers go under #', function () {
    expect(Index::letterOf('Ëxtra Lunge'))->toBe('E')
        ->and(Index::letterOf('45° Leg Press'))->toBe('#')
        ->and(Index::letterOf('bench'))->toBe('B');

    Livewire::actingAs($this->user)->test(Index::class)
        ->call('pickLetter', '#')
        ->assertSee('45° Leg Press')
        ->assertDontSee('Bench Press');
});

test('letters without exercises are disabled and follow the other filters', function () {
    $component = Livewire::actingAs($this->user)->test(Index::class);

    expect($component->get('letters'))->toEqualCanonicalizing(['B', 'C', 'E', '#']);

    $component->set('search', 'press');
    expect($component->get('letters'))->toEqualCanonicalizing(['B', 'C', '#']);

    $component->call('pickLetter', 'Ä')->assertSet('letter', '');
});

test('the letter is kept in the url and cleared with the filters', function () {
    Livewire::actingAs($this->user)->withQueryParams(['letter' => 'C'])->test(Index::class)
        ->assertSet('letter', 'C')
        ->assertSee('Chest Press')
        ->assertDontSee('Bench Press')
        ->call('resetFilters')
        ->assertSet('letter', '');
});
