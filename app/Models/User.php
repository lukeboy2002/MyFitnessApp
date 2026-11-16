<?php

namespace App\Models;

use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Support\Locale;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name', 'username', 'email', 'password', 'weekly_goal_minutes', 'weekly_goal_sessions', 'default_rest_seconds',
    'onboarded_at',
    'birth_date', 'gender', 'height_cm', 'target_weight_kg', 'goal', 'experience',
    'max_heart_rate', 'resting_heart_rate', 'locale',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Fortify gebruikt standaard het login-veld als label in de authenticator-app;
     * dat is geen kolom, dus we tonen het e-mailadres.
     */
    public function twoFactorQrCodeUrl(): string
    {
        return app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
            config('app.name'),
            $this->email,
            Fortify::currentEncrypter()->decrypt($this->two_factor_secret)
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'push_rest' => 'boolean',
            'push_admin' => 'boolean',
            'push_reminder' => 'boolean',
            'push_weekly' => 'boolean',
            'push_inactive' => 'boolean',
            'inactive_reminded_at' => 'datetime',
            'seen_by_admin_at' => 'datetime',
            'banned_at' => 'datetime',
            'banned_until' => 'datetime',
            'birth_date' => 'date',
            'gender' => Gender::class,
            'target_weight_kg' => 'decimal:1',
            'goal' => FitnessGoal::class,
            'experience' => ExperienceLevel::class,
            'onboarded_at' => 'datetime',
        ];
    }

    /** Nieuwe accounts zijn geen admin en hebben nog geen persoonlijke gegevens (ook vóór ze opnieuw uit de database zijn geladen) */
    protected $attributes = [
        'is_admin' => false,
        'seen_by_admin_at' => null,
        'banned_at' => null,
        'banned_until' => null,
        'push_rest' => true,
        'push_admin' => true,
        'push_reminder' => true,
        'push_weekly' => true,
        'push_inactive' => true,
        'inactive_reminded_at' => null,
        'ban_reason' => null,
        'birth_date' => null,
        'gender' => null,
        'height_cm' => null,
        'target_weight_kg' => null,
        'goal' => null,
        'experience' => null,
        'max_heart_rate' => null,
        'resting_heart_rate' => null,
        'locale' => null,
        'weekly_goal_minutes' => null,
        'weekly_goal_sessions' => null,
        'default_rest_seconds' => null,
        'onboarded_at' => null,
    ];

    /** Taal van de app en van mails aan deze gebruiker (null = taal van de browser) */
    public function preferredLocale(): ?string
    {
        return Locale::isAvailable($this->locale) ? $this->locale : null;
    }

    /** Admin: beheert de standaardlijsten (oefeningen, lichaamsdelen, spiergroepen, spieren) en de gebruikers */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /** Door een admin geblokkeerd (een tijdelijke blokkade vervalt vanzelf) */
    public function isBanned(): bool
    {
        return $this->banned_at !== null && ($this->banned_until === null || $this->banned_until->isFuture());
    }

    /** Melding voor de gebruiker zelf, bv. "Je account is geblokkeerd tot 21 nov. 2026, 19:00. Reden: …" */
    public function banMessage(): string
    {
        $message = $this->banned_until
            ? __('Your account has been blocked until :date.', ['date' => $this->banned_until->translatedFormat('j M Y, H:i')])
            : __('Your account has been blocked indefinitely.');

        return $this->ban_reason ? $message.' '.__('Reason: :reason', ['reason' => $this->ban_reason]) : $message;
    }

    /** Nieuw tot een admin het account in de gebruikerslijst heeft aangeklikt (label "Nieuw" en teller in het menu) */
    public function isNew(): bool
    {
        return $this->seen_by_admin_at === null;
    }

    /** Admin: accounts die nog geen admin heeft aangeklikt (behalve je eigen) */
    public function newUsersCount(): int
    {
        if (! $this->isAdmin()) {
            return 0;
        }

        return self::query()
            ->whereKeyNot($this->getKey())
            ->whereNull('seen_by_admin_at')
            ->count();
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    /** Berichten van de admin (pagina Berichten) */
    public function messages(): HasMany
    {
        return $this->hasMany(UserMessage::class);
    }

    public function unreadMessagesCount(): int
    {
        return once(fn () => $this->messages()->whereNull('read_at')->count());
    }

    /** Apparaten waarop pushmeldingen aan staan (rust voorbij) */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    public function bodyWeights(): HasMany
    {
        return $this->hasMany(BodyWeight::class);
    }

    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class);
    }

    /** De laatste weging */
    public function latestBodyWeight(): HasOne
    {
        return $this->hasOne(BodyWeight::class)->latestOfMany('measured_on');
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    /** Body Mass Index uit lengte en de laatste weging */
    public function bmi(): ?float
    {
        $weight = $this->latestBodyWeight?->weight_kg;

        if (! $weight || ! $this->height_cm) {
            return null;
        }

        return round($weight / ($this->height_cm / 100) ** 2, 1);
    }

    /** Je eigen maximale hartslag, anders de schatting uit je leeftijd */
    public function maxHeartRate(): ?int
    {
        return $this->max_heart_rate ?: $this->estimatedMaxHeartRate();
    }

    /** Geschatte maximale hartslag (Tanaka: 208 - 0,7 x leeftijd) */
    public function estimatedMaxHeartRate(): ?int
    {
        $age = $this->age();

        return $age ? (int) round(208 - 0.7 * $age) : null;
    }

    /** Rusthartslag, alleen als die onder je max ligt (anders kunnen we er niet mee rekenen) */
    public function restingHeartRate(): ?int
    {
        $max = $this->maxHeartRate();

        return $this->resting_heart_rate && $max && $this->resting_heart_rate < $max ? $this->resting_heart_rate : null;
    }
}
