<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BodyPartSeeder::class); // lichaamsdelen, spiergroepen, spieren
        $this->call(ExerciseSeeder::class); // standaardoefeningen (na de spiergroepen)

        // Testaccounts en voorbeeldworkouts alleen lokaal: online staat Faker er niet op (composer --no-dev)
        if (app()->isProduction()) {
            return;
        }

        User::factory()->create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@myfitness.test',
            'password' => bcrypt('adminadmin'),
        ]);

        User::factory()->create([
            'name' => 'Antoine Hendriks',
            'username' => 'Antoine',
            'email' => 'antoine@myfitness.test',
            'password' => bcrypt('password'),
        ]);

        $this->call(WorkoutSeeder::class);  // drie voorbeeldworkouts voor Antoine
    }
}
