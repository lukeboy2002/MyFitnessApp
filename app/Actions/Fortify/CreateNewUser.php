<?php

namespace App\Actions\Fortify;

use App\Models\Setting;
use App\Models\User;
use App\Support\Registration;
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
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        abort_unless(Registration::isOpen(), 403, 'Registreren is gesloten.');

        Validator::make($input, [
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
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => Str::lower($input['email']),
            'password' => Hash::make($input['password']),
            'weekly_goal_minutes' => Setting::get('default_weekly_goal_minutes'),
        ]);
    }
}
