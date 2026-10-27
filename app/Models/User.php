<?php

namespace App\Models;

use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
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
        'birth_date' => null,
        'gender' => null,
        'height_cm' => null,
        'target_weight_kg' => null,
        'goal' => null,
        'experience' => null,
        'weekly_goal_sessions' => null,
        'default_rest_seconds' => null,
        'onboarded_at' => null,
    ];

    /** Admin: beheert de standaardlijsten (oefeningen, lichaamsdelen, spiergroepen, spieren) en de gebruikers */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
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

    /** Geschatte maximale hartslag (Tanaka: 208 - 0,7 x leeftijd) */
    public function maxHeartRate(): ?int
    {
        $age = $this->age();

        return $age ? (int) round(208 - 0.7 * $age) : null;
    }
}
