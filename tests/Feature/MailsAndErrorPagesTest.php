<?php

use App\Models\User;
use App\Notifications\NewUserRegistered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('the account mails are in Dutch', function () {
    $user = User::factory()->create(['name' => 'Antoine']);

    $verify = (new VerifyEmail)->toMail($user);
    expect($verify->subject)->toBe('Bevestig je e-mailadres')
        ->and($verify->actionText)->toBe('E-mailadres bevestigen');

    $reset = (new ResetPassword('geheim-token'))->toMail($user);
    expect($reset->subject)->toBe('Nieuw wachtwoord instellen')
        ->and($reset->actionUrl)->toContain('geheim-token');
});

test('mails use the app layout and Dutch texts', function () {
    $user = User::factory()->create(['name' => 'Antoine']);

    $html = (string) (new ResetPassword('geheim-token'))->toMail($user)->render();

    expect($html)->toContain('Hallo Antoine,')
        ->toContain('icons/icon-192.png')
        ->toContain('#00468c')
        ->toContain('Werkt de knop')
        ->not->toContain('Regards')
        ->not->toContain('All rights reserved');
});

test('admins can preview the mails and error pages', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee("Mails en foutpagina's");

    $this->get(route('admin.preview.mail', 'verify-email'))->assertOk()->assertSee('Bevestig je e-mailadres');
    $this->get(route('admin.preview.mail', 'reset-password'))->assertOk()->assertSee('Nieuw wachtwoord kiezen');
    $this->get(route('admin.preview.mail', 'new-user'))->assertOk()->assertSee('Er heeft zich een nieuwe gebruiker geregistreerd');
    $this->get(route('admin.preview.mail', 'bestaat-niet'))->assertNotFound();

    $this->get(route('admin.preview.error', 419))->assertStatus(419)->assertSee('Sessie verlopen');
    $this->get(route('admin.preview.error', 503))->assertStatus(503)->assertSee('Even onderhoud');
    $this->get(route('admin.preview.error', 418))->assertNotFound();
});

test('normal users cannot use the previews', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.preview.mail', 'verify-email'))->assertForbidden()->assertSee('Geen toegang');
    $this->get(route('admin.preview.error', 500))->assertForbidden();
});

test('error pages are in Dutch and in the app style', function () {
    $this->get('/bestaat-niet')->assertNotFound()
        ->assertSee('Niet gevonden')
        ->assertSee('Naar dashboard')
        ->assertSee('icons/icon-192.png');

    Route::get('test/kapot', fn () => abort(500));
    $this->get('test/kapot')->assertStatus(500)->assertSee('Er ging iets mis');

    Route::get('test/eigen-melding', fn () => abort(403, 'Je kunt je eigen adminrechten niet afnemen.'));
    $this->get('test/eigen-melding')->assertForbidden()->assertSee('Je kunt je eigen adminrechten niet afnemen.');

    Route::get('test/te-vaak', fn () => throw new ThrottleRequestsException(headers: ['Retry-After' => 90]));
    $this->get('test/te-vaak')->assertTooManyRequests()->assertSee('Wacht 2 minuten');

    Route::get('test/teapot', fn () => abort(418));
    $this->get('test/teapot')->assertStatus(418)->assertSee('Dat lukte niet')->assertSee('418');
});

test('the admin mail for a new user still works with the new layout', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $html = (string) (new NewUserRegistered(User::factory()->create(['name' => 'Nieuwe Sporter'])))->toMail($admin)->render();

    expect($html)->toContain('Nieuwe Sporter')->toContain('Bekijk de gebruikers');
});
