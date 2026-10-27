<?php

namespace App\Actions\Fortify;

use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Models\Setting;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     * Stap 1 is het account; stap 2 en 3 (persoonlijke gegevens, gewicht en doel) zijn optioneel.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        abort_unless(Registration::isOpen(), 403, 'Registreren is gesloten.');

        // Komma's in kommagetallen (84,2 kg) en lege velden als null
        foreach (['weight_kg', 'target_weight_kg', 'weekly_goal_hours'] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = filled($input[$field]) ? str_replace(',', '.', trim($input[$field])) : null;
            }
        }

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash', 'string', 'max:255', Rule::unique(User::class)],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'birth_date' => ['nullable', 'date', 'after:1900-01-01', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'height_cm' => ['nullable', 'integer', 'between:100,250'],
            'experience' => ['nullable', Rule::enum(ExperienceLevel::class)],
            'goal' => ['nullable', Rule::enum(FitnessGoal::class)],
            'weight_kg' => ['nullable', 'numeric', 'between:20,400'],
            'target_weight_kg' => ['nullable', 'numeric', 'between:30,300'],
            'weekly_goal_hours' => ['nullable', 'numeric', 'min:0.25', 'max:40'],
        ], attributes: [
            'birth_date' => 'geboortedatum',
            'gender' => 'geslacht',
            'height_cm' => 'lengte',
            'experience' => 'ervaring',
            'goal' => 'doel',
            'weight_kg' => 'gewicht',
            'target_weight_kg' => 'streefgewicht',
            'weekly_goal_hours' => 'trainingstijd per week',
        ])->validate();

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make($data['password']),
                'weekly_goal_minutes' => array_key_exists('weekly_goal_hours', $data)
                    ? ($data['weekly_goal_hours'] === null ? null : (int) round($data['weekly_goal_hours'] * 60))
                    : Setting::get('default_weekly_goal_minutes'),
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => ($data['gender'] ?? null) ?: null,
                'height_cm' => $data['height_cm'] ?? null,
                'experience' => ($data['experience'] ?? null) ?: null,
                'goal' => ($data['goal'] ?? null) ?: null,
                'target_weight_kg' => isset($data['target_weight_kg']) ? round($data['target_weight_kg'], 1) : null,
            ]);

            // Het gewicht bij registratie is de eerste weging
            if (isset($data['weight_kg'])) {
                $user->bodyWeights()->create(['measured_on' => today(), 'weight_kg' => round($data['weight_kg'], 1)]);
            }

            return $user;
        });
    }
}
