<?php

use App\Models\Exercise;
use App\Models\User;
use App\Support\ExerciseText;
use Database\Seeders\BodyPartSeeder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed(BodyPartSeeder::class);
    $this->seed(ExerciseSeeder::class);
});

test('every standard exercise has a matching Dutch and German translation', function () {
    foreach (['nl', 'de'] as $locale) {
        App::setLocale($locale);

        Exercise::whereNull('user_id')->each(function (Exercise $exercise) use ($locale) {
            expect(ExerciseText::for($exercise)['description'])
                ->not->toBe($exercise->description, "{$locale}: {$exercise->slug} is niet vertaald");
        });
    }
});

test('the exercise page shows the texts in the language of the user', function () {
    $exercise = Exercise::whereNull('user_id')->where('slug', 'ab-wheel')->firstOrFail();

    $this->actingAs(User::factory()->create(['locale' => 'nl']))->get(route('exercises.show', $exercise))
        ->assertSee('Laat je onderrug niet hol trekken.');

    $this->actingAs(User::factory()->create(['locale' => 'de']))->get(route('exercises.show', $exercise))
        ->assertSee('Lass deinen unteren Rücken nicht ins Hohlkreuz fallen.');

    $this->actingAs(User::factory()->create(['locale' => 'en']))->get(route('exercises.show', $exercise))
        ->assertSee($exercise->tips[0]);
});

test('a changed standard text and an own exercise keep their own text', function () {
    App::setLocale('nl');

    $exercise = Exercise::whereNull('user_id')->where('slug', 'ab-wheel')->firstOrFail();
    $exercise->update(['tips' => ['My own tip']]);
    expect(ExerciseText::for($exercise->fresh())['tips'])->toBe(['My own tip']);

    $own = Exercise::factory()->create([
        'user_id' => User::factory()->create()->id,
        'slug' => 'ab-wheel-copy',
        'description' => 'Mijn eigen omschrijving',
    ]);
    expect(ExerciseText::for($own)['description'])->toBe('Mijn eigen omschrijving');
});
