<?php

use App\Actions\DeleteUserAccount;
use App\Enums\ContactStatus;
use App\Livewire\Admin\Contact as AdminContact;
use App\Livewire\Contact\Index;
use App\Livewire\Contact\Show;
use App\Livewire\Exercises\Form as ExerciseForm;
use App\Models\ContactRequest;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Notifications\NewContactMessage;
use Database\Seeders\BodyPartSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Beheer']);
    $this->user = User::factory()->create(['name' => 'Jan', 'locale' => 'nl']);
});

function askQuestion(User $user, string $topic = 'exercise', string $subject = 'Hip Thrust (Machine)', string $body = 'Kan die erbij?'): ContactRequest
{
    Livewire::actingAs($user)->test(Index::class)
        ->set('topic', $topic)->set('subject', $subject)->set('body', $body)
        ->call('send')
        ->assertHasNoErrors();

    return $user->contactRequests()->latest('id')->first();
}

test('a user can ask the admin a question, and the admins get an email', function () {
    $request = askQuestion($this->user);

    expect($request)
        ->topic->value->toBe('exercise')
        ->subject->toBe('Hip Thrust (Machine)')
        ->status->toBe(ContactStatus::Open)
        ->and($request->replies()->sole()->body)->toBe('Kan die erbij?');

    Notification::assertSentTo($this->admin, NewContactMessage::class);
    Notification::assertNotSentTo($this->user, NewContactMessage::class);

    $this->actingAs($this->user)->get(route('contact'))->assertOk()->assertSee('Hip Thrust (Machine)');
});

test('the question needs a subject and a message, and the number per hour is limited', function () {
    Livewire::actingAs($this->user)->test(Index::class)
        ->set('subject', '')->set('body', '')->call('send')
        ->assertHasErrors(['subject', 'body']);

    foreach (range(1, Index::PER_HOUR) as $i) {
        askQuestion($this->user, 'question', "Vraag {$i}");
    }

    Livewire::actingAs($this->user)->test(Index::class)
        ->set('topic', 'question')->set('subject', 'Nog een')->set('body', 'x')->call('send')
        ->assertHasErrors('body');
    expect($this->user->contactRequests()->count())->toBe(Index::PER_HOUR);
});

test('the admin answers: status answered, a message in Messages and the user sees it in the conversation', function () {
    $request = askQuestion($this->user);
    $this->travel(5)->minutes();

    Livewire::actingAs($this->admin)->test(AdminContact::class)
        ->assertSee('Hip Thrust (Machine)')
        ->call('choose', $request->id)
        ->set('answer', 'Toegevoegd!')
        ->call('reply')
        ->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(ContactStatus::Answered)
        ->and($request->fresh()->hasUnreadAnswer())->toBeTrue()
        ->and($this->user->messages()->sole())
        ->title->toBe('Antwoord op je vraag')
        ->body->toBe("Hip Thrust (Machine)\n\nToegevoegd!")
        ->url->toBe(route('contact.show', $request, absolute: false));

    // Gesprek openen: antwoord zichtbaar, bericht in Berichten gelezen, geen bolletje meer
    $this->travel(5)->minutes();
    Livewire::actingAs($this->user)->test(Show::class, ['contactRequest' => $request])
        ->assertSee('Toegevoegd!');

    expect($this->user->messages()->sole()->read_at)->not->toBeNull()
        ->and($request->fresh()->hasUnreadAnswer())->toBeFalse();
});

test('a reaction from the user opens the question again and emails the admins', function () {
    $request = askQuestion($this->user);
    $request->addReply($this->admin, 'Welke spiergroep?', asAdmin: true);
    $request->update(['status' => ContactStatus::Closed]);

    Livewire::actingAs($this->user)->test(Show::class, ['contactRequest' => $request])
        ->set('body', 'Billen')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('body', '');

    expect($request->fresh()->status)->toBe(ContactStatus::Open)
        ->and($request->replies()->count())->toBe(3);
    Notification::assertSentToTimes($this->admin, NewContactMessage::class, 2);
});

test('the admin can close and reopen a question; the menu counts the open questions', function () {
    $request = askQuestion($this->user, 'idea', 'Donkere grafieken');
    askQuestion($this->user, 'bug', 'Grafiek leeg');

    expect($this->admin->openContactCount())->toBe(2)
        ->and($this->user->openContactCount())->toBe(0);

    $component = Livewire::actingAs($this->admin)->test(AdminContact::class)->call('choose', $request->id)->call('close');
    expect($request->fresh()->status)->toBe(ContactStatus::Closed)
        ->and(ContactRequest::open()->count())->toBe(1);

    $component->call('reopen');
    expect($request->fresh()->status)->toBe(ContactStatus::Open);

    $this->actingAs($this->admin)->get(route('admin.contact', ['open' => $request->id]))->assertOk()->assertSee('Donkere grafieken');
});

test('only the owner sees a conversation, and only admins see the admin page', function () {
    $request = askQuestion($this->user);
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('contact.show', $request))->assertNotFound();
    $this->actingAs($this->user)->get(route('contact.show', $request))->assertOk();
    $this->actingAs($this->user)->get(route('admin.contact'))->assertForbidden();
    Livewire::actingAs($this->user)->test(AdminContact::class)->assertForbidden();
});

test('the pages refresh themselves every 10 seconds', function () {
    $request = askQuestion($this->user);

    $this->actingAs($this->user)->get(route('contact.show', $request))->assertSee('wire:poll.10s', false);
    $this->actingAs($this->admin)->get(route('admin.contact'))->assertSee('wire:poll.10s', false);
});

test('from an exercise request the admin creates a standard exercise with that name', function () {
    $this->seed(BodyPartSeeder::class);
    $group = MuscleGroup::whereNull('user_id')->where('slug', 'glutes')->value('id');

    Livewire::withQueryParams(['name' => 'Hip Thrust (Machine)', 'standard' => 1])
        ->actingAs($this->admin)->test(ExerciseForm::class)
        ->assertSet('name', 'Hip Thrust (Machine)')
        ->assertSet('standard', true)
        ->set('primary_muscle_group_id', $group)
        ->call('save')
        ->assertHasNoErrors();

    $exercise = Exercise::where('name', 'Hip Thrust (Machine)')->sole();
    expect($exercise->user_id)->toBeNull();

    // Eigen spiergroepen van de admin staan alleen in de lijst bij een eigen oefening; omzetten haalt ze uit de keuze
    $own = MuscleGroup::factory()->create(['user_id' => $this->admin->id, 'name' => 'Mijn groep']);
    Livewire::withQueryParams([])->actingAs($this->admin)->test(ExerciseForm::class)
        ->assertSet('standard', false)
        ->assertSee('Mijn groep')
        ->set('primary_muscle_group_id', $own->id)
        ->set('secondary_muscle_group_ids', [$own->id, $group])
        ->set('standard', true)
        ->assertDontSee('Mijn groep')
        ->assertSet('primary_muscle_group_id', null)
        ->assertSet('secondary_muscle_group_ids', [$group]);

    // Een gewone gebruiker kan dat niet
    Livewire::withQueryParams(['name' => 'Eigen', 'standard' => 1])
        ->actingAs($this->user)->test(ExerciseForm::class)
        ->assertSet('standard', false)
        ->set('standard', true)
        ->set('primary_muscle_group_id', $group)
        ->call('save');
    expect(Exercise::where('name', 'Eigen')->sole()->user_id)->toBe($this->user->id);
});

test('deleting an account removes its questions', function () {
    $request = askQuestion($this->user);

    app(DeleteUserAccount::class)->handle($this->user);

    expect(ContactRequest::find($request->id))->toBeNull();
});

test('the migration creates and removes the tables', function () {
    $migration = require database_path('migrations/2026_11_16_220000_create_contact_requests_table.php');

    $migration->down();
    expect(Schema::hasTable('contact_requests'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('contact_replies'))->toBeTrue();
});
