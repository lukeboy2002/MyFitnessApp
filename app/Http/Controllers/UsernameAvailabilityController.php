<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Registreren, stap 1: is deze gebruikersnaam nog vrij? (zelfde regels als CreateNewUser) */
class UsernameAvailabilityController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(Registration::isOpen(), 404);

        $username = trim((string) $request->query('username'));

        if (! preg_match('/^[\pL\pM\pN_-]+$/u', $username) || mb_strlen($username) > 255) {
            return response()->json(['available' => false, 'message' => 'Alleen letters, cijfers, - en _ (geen spaties).']);
        }

        $available = ! User::where('username', $username)->exists();

        return response()->json([
            'available' => $available,
            'message' => $available ? 'Deze gebruikersnaam is nog vrij.' : 'Deze gebruikersnaam is al bezet.',
        ]);
    }
}
